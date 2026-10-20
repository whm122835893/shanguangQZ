<script setup lang="ts">
/** 用户详情（契约 §8.3）：基础信息 / 钱包（资金恒等式）/ 藏品 / 盲盒 / 邀请 + 危险操作区 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import {
  userDetail,
  userWallet,
  userCollectibles,
  userBlindboxes,
  userInvites,
  resetTxPassword,
  forceLogoutUser,
  addBlacklist,
  removeBlacklist,
  recoverCollectible,
} from '@/api/user'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type DetailData = Record<string, unknown>

const route = useRoute()
const router = useRouter()
const userId = Number(route.params.id)

function goBack(): void {
  void router.push('/user')
}

/* ---------- Tab1 基础信息：userDetail 全字段 descriptions（嵌套 counts 单独追加） ---------- */

const STATUS_TEXT: Record<string, string> = {
  normal: '正常',
  freeze: '已冻结',
  frozen: '已冻结',
  black: '黑名单',
}

/** descriptions 字段中文映射，未收录字段展示原 key */
const FIELD_LABELS: Record<string, string> = {
  id: '用户ID',
  username: '用户名',
  nickname: '昵称',
  mobile_masked: '手机号（脱敏）',
  mobile: '手机号（脱敏）',
  email: '邮箱',
  avatar: '头像',
  level: '等级',
  gender: '性别',
  bio: '简介',
  money: '余额',
  agent_money: '代理收益',
  score: '积分',
  status: '状态',
  is_blacklisted: '黑名单',
  blacklist_reason: '黑名单原因',
  is_realname: '实名认证',
  inviter_code: '邀请码',
  inviter_user_id: '邀请人ID',
  invited_count: '已邀请人数',
  collection_num: '藏品持有数',
  blind_box_num: '盲盒数',
  reg_time: '注册时间',
  jointime: '注册时间',
  last_login: '最后登录',
}

function fmtField(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '-'
  if (key === 'money' || key === 'agent_money') return '¥' + money(String(value))
  if (key === 'reg_time' || key === 'jointime' || key === 'last_login') return fmtDateTime(String(value))
  if (key === 'status') return STATUS_TEXT[String(value)] ?? String(value)
  if (key === 'is_realname') return Number(value) === 1 ? '已实名' : '未实名'
  if (key === 'is_blacklisted') return Number(value) === 1 ? '是' : '否'
  return String(value)
}

const detail = ref<DetailData | null>(null)
const wallet = ref<DetailData | null>(null)
const loading = ref(false)

const entries = computed<Array<{ key: string; label: string; value: string }>>(() => {
  const d = detail.value
  if (!d) return []
  const rows = Object.entries(d)
    .filter(([k]) => k !== 'counts')
    .map(([k, v]) => ({ key: k, label: FIELD_LABELS[k] ?? k, value: fmtField(k, v) }))
  const counts = d.counts
  if (counts !== null && typeof counts === 'object') {
    const c = counts as Record<string, unknown>
    rows.push(
      { key: 'c_orders', label: '订单数', value: String(c.orders ?? '-') },
      { key: 'c_holdings', label: '持仓数量', value: String(c.holdings ?? '-') },
      { key: 'c_listings', label: '在售挂单', value: String(c.listings ?? '-') },
    )
  }
  return rows
})

/* ---------- Tab2 钱包：余额 + 资金恒等式提示 ---------- */

const walletRows = computed<Array<{ key: string; label: string; value: string }>>(() => {
  const w = wallet.value
  if (!w) return []
  return [
    { key: 'balance', label: '当前余额（balance）', value: '¥' + money(String(w.balance ?? '0')) },
    { key: 'ledger_in', label: '累计入账（ledger_in）', value: '¥' + money(String(w.ledger_in ?? '0')) },
    { key: 'ledger_out', label: '累计出账（ledger_out）', value: '¥' + money(String(w.ledger_out ?? '0')) },
    { key: 'ledger_balance', label: '账本余额（ledger_balance）', value: '¥' + money(String(w.ledger_balance ?? '0')) },
    { key: 'total_recharge', label: '累计充值（total_recharge）', value: '¥' + money(String(w.total_recharge ?? '0')) },
  ]
})

/* ---------- Tab3 藏品 / Tab4 盲盒：分页表格（Tab 首次激活时懒加载） ---------- */

type CollectibleRowVM = {
  uc_id: number
  name: string
  image: string
  num: number
  on_sale: number
  locked: number
  obtained_at: string
  recoverable: boolean
}

const colRows = ref<CollectibleRowVM[]>([])
const colPage = ref(1)
const colPageSize = ref(20)
const colTotal = ref(0)
const colLoading = ref(false)

async function fetchCollectibles(): Promise<void> {
  colLoading.value = true
  try {
    const res = await userCollectibles(userId, { page: colPage.value, page_size: colPageSize.value })
    colRows.value = (res.list ?? []) as CollectibleRowVM[]
    colTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    colLoading.value = false
  }
}

function onColPageChange(p: number, s: number): void {
  colPage.value = p
  colPageSize.value = s
  void fetchCollectibles()
}

type BlindboxRowVM = {
  id: number
  name: string
  num: number
  obtained_at: string
}

const bbRows = ref<BlindboxRowVM[]>([])
const bbPage = ref(1)
const bbPageSize = ref(20)
const bbTotal = ref(0)
const bbLoading = ref(false)

async function fetchBlindboxes(): Promise<void> {
  bbLoading.value = true
  try {
    const res = await userBlindboxes(userId, { page: bbPage.value, page_size: bbPageSize.value })
    bbRows.value = (res.list ?? []) as BlindboxRowVM[]
    bbTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    bbLoading.value = false
  }
}

function onBbPageChange(p: number, s: number): void {
  bbPage.value = p
  bbPageSize.value = s
  void fetchBlindboxes()
}

/* ---------- Tab5 邀请：userInvites 表格 + 统计 ---------- */

const REWARD_LABELS: Record<string, string | undefined> = {
  collectible: '藏品',
  points: '积分',
  balance: '余额',
  draw_chance: '抽奖次数',
  priority_qualification: '优先购资格',
  eligibility_qualification: '资格购资格',
  blindbox: '盲盒',
}

type InviteRowVM = {
  id: number
  nickname: string
  mobile: string
  reward_type: string
  status: number
  time: string
}

type InviteStats = { invited_count: number; inviter_code: string; inviter_user_id: number }

const invRows = ref<InviteRowVM[]>([])
const invStats = ref<InviteStats | null>(null)
const invLoading = ref(false)

async function fetchInvites(): Promise<void> {
  invLoading.value = true
  try {
    const res = (await userInvites(userId)) as DetailData
    const stats = (res.stats ?? {}) as Record<string, unknown>
    invStats.value = {
      invited_count: Number(stats.invited_count ?? 0),
      inviter_code: String(stats.inviter_code ?? ''),
      inviter_user_id: Number(stats.inviter_user_id ?? 0),
    }
    const raw = res.list
    invRows.value = Array.isArray(raw) ? (raw as InviteRowVM[]) : []
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    invLoading.value = false
  }
}

function rewardLabel(row: InviteRowVM): string {
  const key = String(row.reward_type ?? '')
  return key === '' ? '-' : (REWARD_LABELS[key] ?? key)
}

function inviteStatus(row: InviteRowVM): { text: string; type: 'success' | 'info' } {
  return Number(row.status) === 1 ? { text: '已发放', type: 'success' } : { text: '未发放', type: 'info' }
}

/* ---------- Tab 懒加载：首次激活才请求 ---------- */

const activeTab = ref('basic')
const loadedTabs = new Set<string>(['basic', 'wallet'])

function loadTab(name: string | number): void {
  const key = String(name)
  if (loadedTabs.has(key)) return
  loadedTabs.add(key)
  if (key === 'collectibles') void fetchCollectibles()
  else if (key === 'blindboxes') void fetchBlindboxes()
  else if (key === 'invites') void fetchInvites()
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const [d, w] = await Promise.all([userDetail(userId), userWallet(userId)])
    detail.value = d
    wallet.value = w
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/* ---------- 危险操作区：全部走 ConfirmDialog（原因 + 管理员密码） ---------- */

type DangerAction = 'resetTxPassword' | 'forceLogout' | 'addBlacklist' | 'removeBlacklist' | 'recoverCollectible'

const DANGER_META: Record<DangerAction, { title: string; message: string }> = {
  resetTxPassword: { title: '重置交易密码', message: '将清空该用户的交易密码，用户需自行重新设置；操作将写入审计日志。' },
  forceLogout: { title: '强制登出', message: '将吊销该用户全部 C 端会话并强制下线；操作将写入审计日志。' },
  addBlacklist: { title: '加入黑名单', message: '加入黑名单后该用户将被禁止登录并立即踢下线；操作将写入审计日志。' },
  removeBlacklist: { title: '移出黑名单', message: '移出后该用户恢复正常登录；操作将写入审计日志。' },
  recoverCollectible: { title: '强制回收藏品', message: '将删除该用户的藏品持有记录并归还库存池，二次流转中的藏品会被拦截；操作不可逆且写入审计日志。' },
}

const dangerVisible = ref(false)
const dangerAction = ref<DangerAction | null>(null)
const dangerLoading = ref(false)
const recoverId = ref<number | null>(null)

const dangerMeta = computed(() => {
  const action = dangerAction.value
  return action ? DANGER_META[action] : { title: '', message: '' }
})

/** ConfirmDialog 确认文本：回收藏品时要求手动输入 user_collectible_id 复核 */
const dangerConfirmText = computed(() =>
  dangerAction.value === 'recoverCollectible' && recoverId.value !== null ? String(recoverId.value) : undefined,
)

const isBlacklisted = computed(() => Number(detail.value?.is_blacklisted) === 1)

function openDanger(action: DangerAction): void {
  if (action === 'recoverCollectible' && (recoverId.value === null || recoverId.value <= 0)) {
    ElMessage.warning('请先输入要回收的 user_collectible_id')
    return
  }
  dangerAction.value = action
  dangerVisible.value = true
}

/** 藏品行内快捷回收：自动带入持有记录 ID */
function recoverFromRow(row: CollectibleRowVM): void {
  recoverId.value = row.uc_id
  openDanger('recoverCollectible')
}

async function onDangerConfirm(payload: { reason: string; password: string }): Promise<void> {
  const action = dangerAction.value
  if (!action) return
  dangerLoading.value = true
  try {
    switch (action) {
      case 'resetTxPassword':
        await resetTxPassword(userId, payload.password, payload.reason)
        break
      case 'forceLogout':
        await forceLogoutUser(userId, payload.password, payload.reason)
        break
      case 'addBlacklist':
        await addBlacklist(userId, payload.reason, payload.password)
        break
      case 'removeBlacklist':
        await removeBlacklist(userId, payload.reason, payload.password)
        break
      case 'recoverCollectible':
        await recoverCollectible(userId, {
          user_collectible_id: recoverId.value ?? 0,
          reason: payload.reason,
          password: payload.password,
        })
        recoverId.value = null
        void fetchCollectibles()
        break
    }
    ElMessage.success('操作成功')
    dangerVisible.value = false
    void load() // 状态 / 黑名单标记可能已变化
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    dangerLoading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="detail-header">
        <h3 style="margin: 0">用户详情 #{{ userId }}</h3>
        <el-button @click="goBack">返回列表</el-button>
      </div>

      <el-tabs v-model="activeTab" @tab-change="loadTab">
        <!-- Tab1 基础信息 -->
        <el-tab-pane label="基础信息" name="basic">
          <el-descriptions v-if="detail" :column="3" border>
            <el-descriptions-item v-for="e in entries" :key="e.key" :label="e.label">
              {{ e.value }}
            </el-descriptions-item>
          </el-descriptions>
          <el-empty v-else description="暂无数据" :image-size="64" />
        </el-tab-pane>

        <!-- Tab2 钱包 -->
        <el-tab-pane label="钱包" name="wallet">
          <el-alert
            type="info"
            :closable="false"
            show-icon
            style="margin-bottom: 16px"
            title="资金恒等式：账本余额（ledger_balance）= 累计入账（ledger_in）− 累计出账（ledger_out）"
            description="钱包合计以账本 SUM 为准，balance 仅作展示；若两者不一致，请以「资金流水」交叉核对并上报风控。"
          />
          <el-descriptions v-if="wallet" :column="2" border>
            <el-descriptions-item v-for="e in walletRows" :key="e.key" :label="e.label">
              {{ e.value }}
            </el-descriptions-item>
          </el-descriptions>
          <el-empty v-else description="暂无数据" :image-size="64" />
        </el-tab-pane>

        <!-- Tab3 藏品 -->
        <el-tab-pane label="藏品" name="collectibles">
          <DataTable :data="colRows" :loading="colLoading" empty-text="暂无藏品持有记录">
            <el-table-column prop="uc_id" label="持有ID" width="90" />
            <el-table-column label="藏品" min-width="200">
              <template #default="{ row }">
                <div class="asset-cell">
                  <el-image
                    v-if="row.image"
                    :src="row.image"
                    fit="cover"
                    class="asset-img"
                    :preview-src-list="[row.image]"
                    preview-teleported
                    hide-on-click-modal
                  />
                  <span>{{ row.name || '-' }}</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="num" label="数量" width="80" />
            <el-table-column prop="on_sale" label="在售" width="80" />
            <el-table-column prop="locked" label="锁定" width="80" />
            <el-table-column label="获得时间" width="180">
              <template #default="{ row }">{{ fmtDateTime(row.obtained_at) }}</template>
            </el-table-column>
            <el-table-column label="回收" width="130" fixed="right">
              <template #default="{ row }">
                <el-tag v-if="!row.recoverable" type="info" size="small">流转中</el-tag>
                <el-button v-else v-permission="'user:recover'" link type="danger" @click="recoverFromRow(row as CollectibleRowVM)">
                  可回收
                </el-button>
              </template>
            </el-table-column>
          </DataTable>
          <Pagination :page="colPage" :page-size="colPageSize" :total="colTotal" :loading="colLoading" @change="onColPageChange" />
        </el-tab-pane>

        <!-- Tab4 盲盒 -->
        <el-tab-pane label="盲盒" name="blindboxes">
          <DataTable :data="bbRows" :loading="bbLoading" empty-text="暂无盲盒持有记录（开启后即发放子藏品）">
            <el-table-column prop="id" label="持有ID" width="90" />
            <el-table-column prop="name" label="盲盒" min-width="200" show-overflow-tooltip />
            <el-table-column prop="num" label="数量" width="80" />
            <el-table-column label="获得时间" width="180">
              <template #default="{ row }">{{ fmtDateTime(row.obtained_at) }}</template>
            </el-table-column>
          </DataTable>
          <Pagination :page="bbPage" :page-size="bbPageSize" :total="bbTotal" :loading="bbLoading" @change="onBbPageChange" />
        </el-tab-pane>

        <!-- Tab5 邀请 -->
        <el-tab-pane label="邀请" name="invites">
          <el-descriptions v-if="invStats" :column="3" border style="margin-bottom: 12px">
            <el-descriptions-item label="邀请码">{{ invStats.inviter_code || '-' }}</el-descriptions-item>
            <el-descriptions-item label="已邀请人数">{{ invStats.invited_count }}</el-descriptions-item>
            <el-descriptions-item label="上级用户ID">{{ invStats.inviter_user_id || '-' }}</el-descriptions-item>
          </el-descriptions>
          <DataTable :data="invRows" :loading="invLoading" empty-text="暂无邀请记录">
            <el-table-column prop="id" label="记录ID" width="90" />
            <el-table-column label="被邀请人昵称" min-width="140" show-overflow-tooltip>
              <template #default="{ row }">{{ row.nickname || '-' }}</template>
            </el-table-column>
            <el-table-column label="手机号（脱敏）" width="140">
              <template #default="{ row }">{{ row.mobile || '-' }}</template>
            </el-table-column>
            <el-table-column label="奖励类型" width="130">
              <template #default="{ row }">{{ rewardLabel(row as InviteRowVM) }}</template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="inviteStatus(row as InviteRowVM).type" size="small">
                  {{ inviteStatus(row as InviteRowVM).text }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="时间" width="180">
              <template #default="{ row }">{{ fmtDateTime(row.time) }}</template>
            </el-table-column>
          </DataTable>
        </el-tab-pane>
      </el-tabs>
    </div>

    <!-- 危险操作区 -->
    <div class="card">
      <div class="danger-bar">危险操作区：以下操作不可逆且全部写入审计日志，请谨慎执行。</div>
      <el-space wrap>
        <el-button v-permission="'user:manage'" type="warning" plain @click="openDanger('resetTxPassword')">重置交易密码</el-button>
        <el-button v-permission="'user:manage'" type="warning" plain @click="openDanger('forceLogout')">强制登出</el-button>
        <el-button v-if="!isBlacklisted" v-permission="'user:blacklist'" type="danger" plain @click="openDanger('addBlacklist')">
          加入黑名单
        </el-button>
        <el-button v-if="isBlacklisted" v-permission="'user:blacklist'" type="success" plain @click="openDanger('removeBlacklist')">
          移出黑名单
        </el-button>
      </el-space>
      <el-divider style="margin: 16px 0" />
      <div class="recover-line">
        <span class="recover-label">强制回收藏品（user_collectible_id）：</span>
        <el-input-number v-model="recoverId" :min="1" :controls="false" placeholder="持有记录ID" style="width: 180px" />
        <el-button v-permission="'user:recover'" type="danger" @click="openDanger('recoverCollectible')">强制回收藏品</el-button>
      </div>
    </div>

    <ConfirmDialog
      v-model="dangerVisible"
      :title="dangerMeta.title"
      :message="dangerMeta.message"
      :confirm-text="dangerConfirmText"
      :loading="dangerLoading"
      @confirm="onDangerConfirm"
    />
  </div>
</template>

<style scoped>
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.asset-cell { display: flex; align-items: center; gap: 8px; }
.asset-img { width: 36px; height: 36px; border-radius: 4px; flex-shrink: 0; }
.recover-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.recover-label { font-size: 13px; color: #606266; }
</style>
