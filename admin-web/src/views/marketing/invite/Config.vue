<script setup lang="ts">
/** 邀请配置（契约 §8.11.3，106~108）：配置回显 + tiers JSON 编辑（密码确认）+ 邀请记录分页表 */
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { getInviteConfig, updateInviteConfig, listInviteRecords } from '@/api/marketing'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

/* ---------- 配置（106/107） ---------- */

const loading = ref(false)
const confirmVisible = ref(false)
const submitting = ref(false)

const form = reactive<{ is_enabled: number; register_reward: number | undefined }>({
  is_enabled: 1,
  register_reward: 0,
})
const tiersText = ref('[]')

/** 邀请档位预览行 */
type TierItemVM = { level: number; reward: string }

/** tiers JSON 实时解析（需为数组，元素含 level ≥ 1 与 reward） */
const parsedTiers = computed<{ ok: boolean; rows: TierItemVM[] }>(() => {
  try {
    const parsed: unknown = JSON.parse(tiersText.value)
    if (!Array.isArray(parsed)) return { ok: false, rows: [] }
    const rows: TierItemVM[] = []
    for (const item of parsed) {
      if (typeof item !== 'object' || item === null || Array.isArray(item)) return { ok: false, rows: [] }
      const rec = item as Record<string, unknown>
      rows.push({ level: Number(rec.level ?? 0), reward: money(rec.reward as string | number | null | undefined) })
    }
    return { ok: true, rows }
  } catch {
    return { ok: false, rows: [] }
  }
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getInviteConfig()
    form.is_enabled = Number(data.is_enabled) === 1 ? 1 : 0
    const tiers = data.tiers
    tiersText.value = JSON.stringify(Array.isArray(tiers) ? tiers : [], null, 2)
    const rr = data.register_reward
    const rrNum = Number(rr)
    form.register_reward = Number.isFinite(rrNum) ? rrNum : 0
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  if (!parsedTiers.value.ok) {
    ElMessage.error('tiers JSON 格式有误：需为数组，元素形如 {"level":1,"reward":"1.00"} 且 level ≥ 1')
    return
  }
  if (parsedTiers.value.rows.some((t) => !(t.level >= 1))) {
    ElMessage.error('档位级别（level）必须 ≥ 1')
    return
  }
  confirmVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await updateInviteConfig({
      is_enabled: form.is_enabled,
      tiers: parsedTiers.value.rows.map((t) => ({ level: t.level, reward: t.reward })),
      register_reward: String(form.register_reward ?? 0),
      password: payload.password,
    })
    ElMessage.success('邀请配置已更新')
    confirmVisible.value = false
    await load()
  } catch {
    /* 失败已由请求拦截器提示，弹窗保持打开便于修正 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 邀请记录（108） ---------- */

/** 记录行（后端 inviteRecords 口径） */
type InviteRecordVM = {
  id: number
  inviter_id: number
  inviter_nickname: string
  invitee_id: number
  invitee_nickname: string
  invitee_phone: string
  reward_type: string
  status: number
  create_time: string
}

function asRecord(row: unknown): InviteRecordVM {
  return row as InviteRecordVM
}

function rewardTypeText(t: string): string {
  if (t === 'balance') return '余额'
  if (t === 'point') return '积分'
  return t || '-'
}

function statusMeta(s: number): { label: string; type: 'success' | 'warning' | 'info' } {
  if (s === 1) return { label: '已发放', type: 'success' }
  if (s === 0) return { label: '待发放', type: 'warning' }
  return { label: `状态${s}`, type: 'info' }
}

const searchFields: SearchField[] = [
  { key: 'inviter_id', label: '邀请人ID' },
  { key: 'date_range', label: '邀请时间', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const listLoading = ref(false)

function buildParams(): Record<string, unknown> {
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
  const uid = String(query.value.inviter_id ?? '').trim()
  if (uid !== '') params.inviter_id = uid
  const range = query.value.date_range
  if (Array.isArray(range) && range.length === 2) {
    const [s, e] = range as [string, string]
    params.date_start = s
    params.date_end = e
  }
  return params
}

async function fetchRecords(): Promise<void> {
  listLoading.value = true
  try {
    const res = await listInviteRecords(buildParams())
    rows.value = res.list
    total.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    listLoading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchRecords()
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchRecords()
}

onMounted(() => {
  void load()
  void fetchRecords()
})
</script>

<template>
  <div>
    <div v-loading="loading" class="card">
      <h3 style="margin-top: 0">邀请配置</h3>
      <el-form label-width="140px" style="max-width: 720px" @submit.prevent="openSave">
        <el-form-item label="启用邀请奖励">
          <el-switch v-model="form.is_enabled" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="档位规则（JSON）" required>
          <el-input
            v-model="tiersText"
            type="textarea"
            :rows="8"
            placeholder='[{"level":1,"reward":"2.00"},{"level":5,"reward":"5.00"}]'
          />
          <div style="width: 100%; font-size: 12px; color: #909399; margin-top: 4px">
            数组元素：level 累计邀请人数档位（≥ 1），reward 达标奖励金额
          </div>
        </el-form-item>
        <el-form-item v-if="parsedTiers.ok && parsedTiers.rows.length > 0" label="解析预览">
          <el-table :data="parsedTiers.rows" size="small" style="width: 360px">
            <el-table-column prop="level" label="邀请人数档位" width="140" />
            <el-table-column label="奖励金额">
              <template #default="{ row }">¥{{ money((row as TierItemVM).reward) }}</template>
            </el-table-column>
          </el-table>
        </el-form-item>
        <el-form-item label="被邀请人注册奖励（元）">
          <el-input-number v-model="form.register_reward" :min="0" :precision="2" :step="0.5" />
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'marketing:invite:config'" type="primary" native-type="submit">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">邀请记录</h3>
      <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onSearch" />

      <DataTable :data="rows" :loading="listLoading" empty-text="暂无邀请记录">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="邀请人" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRecord(row).inviter_id }}
            {{ asRecord(row).inviter_nickname ? `（${asRecord(row).inviter_nickname}）` : '' }}
          </template>
        </el-table-column>
        <el-table-column label="被邀请人" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRecord(row).invitee_id }}
            {{ asRecord(row).invitee_nickname ? `（${asRecord(row).invitee_nickname}）` : '' }}
            <span v-if="asRecord(row).invitee_phone" style="color: #909399">{{ asRecord(row).invitee_phone }}</span>
          </template>
        </el-table-column>
        <el-table-column label="奖励类型" width="110">
          <template #default="{ row }">{{ rewardTypeText(asRecord(row).reward_type) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="statusMeta(asRecord(row).status).type">
              {{ statusMeta(asRecord(row).status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="180">
          <template #default="{ row }">{{ fmtDateTime(asRecord(row).create_time) }}</template>
        </el-table-column>
      </DataTable>

      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="listLoading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="修改邀请配置"
      message="修改将影响全部 C 端用户的邀请奖励结算，请确认档位与奖励金额无误；操作需管理员密码验证并写入审计日志。"
      :need-reason="false"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
