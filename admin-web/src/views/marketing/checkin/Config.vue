<script setup lang="ts">
/** 签到配置（契约 §8.11.2，103~105）：配置回显 + JSON 编辑（密码确认）+ 签到记录分页表 */
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { getCheckinConfig, updateCheckinConfig, listCheckinRecords } from '@/api/marketing'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

/* ---------- 配置（103/104） ---------- */

const loading = ref(false)
const confirmVisible = ref(false)
const submitting = ref(false)

/** el-switch 数字开关（后端 is_enabled: 1/0） */
const form = reactive<{ is_enabled: number }>({ is_enabled: 1 })
const rewardsText = ref('[]')

/** 奖励档位预览行 */
type RewardItemVM = { day: number; amount: string }

/** rewards JSON 实时解析（需为数组，元素含 day ≥ 1 与 amount） */
const parsedRewards = computed<{ ok: boolean; rows: RewardItemVM[] }>(() => {
  try {
    const parsed: unknown = JSON.parse(rewardsText.value)
    if (!Array.isArray(parsed)) return { ok: false, rows: [] }
    const rows: RewardItemVM[] = []
    for (const item of parsed) {
      if (typeof item !== 'object' || item === null || Array.isArray(item)) return { ok: false, rows: [] }
      const rec = item as Record<string, unknown>
      rows.push({ day: Number(rec.day ?? 0), amount: money(rec.amount as string | number | null | undefined) })
    }
    return { ok: true, rows }
  } catch {
    return { ok: false, rows: [] }
  }
})

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getCheckinConfig()
    form.is_enabled = Number(data.is_enabled) === 1 ? 1 : 0
    const rewards = data.rewards
    rewardsText.value = JSON.stringify(Array.isArray(rewards) ? rewards : [], null, 2)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  if (!parsedRewards.value.ok) {
    ElMessage.error('rewards JSON 格式有误：需为数组，元素形如 {"day":1,"amount":"1.00"} 且 day ≥ 1')
    return
  }
  if (parsedRewards.value.rows.some((r) => !(r.day >= 1))) {
    ElMessage.error('奖励天数（day）必须 ≥ 1')
    return
  }
  confirmVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await updateCheckinConfig({
      is_enabled: form.is_enabled,
      rewards: parsedRewards.value.rows.map((r) => ({ day: r.day, amount: r.amount })),
      password: payload.password,
    })
    ElMessage.success('签到配置已更新')
    confirmVisible.value = false
    await load()
  } catch {
    /* 失败已由请求拦截器提示，弹窗保持打开便于修正 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 签到记录（105） ---------- */

/** 记录行（后端 checkinRecords 口径） */
type CheckinRecordVM = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  checkin_date: string
  successions: number
  reward_type: string
  reward_status: number
  create_time: string
}

function asRecord(row: unknown): CheckinRecordVM {
  return row as CheckinRecordVM
}

function rewardTypeText(t: string): string {
  if (t === 'balance') return '余额'
  if (t === 'point') return '积分'
  return t || '-'
}

function rewardStatusMeta(s: number): { label: string; type: 'success' | 'warning' | 'info' } {
  if (s === 1) return { label: '已发放', type: 'success' }
  if (s === 0) return { label: '待发放', type: 'warning' }
  return { label: `状态${s}`, type: 'info' }
}

const searchFields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  { key: 'date_range', label: '签到日期', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const listLoading = ref(false)

function buildParams(): Record<string, unknown> {
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
  const uid = String(query.value.user_id ?? '').trim()
  if (uid !== '') params.user_id = uid
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
    const res = await listCheckinRecords(buildParams())
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
      <h3 style="margin-top: 0">签到配置</h3>
      <el-form label-width="120px" style="max-width: 720px" @submit.prevent="openSave">
        <el-form-item label="启用签到">
          <el-switch v-model="form.is_enabled" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="奖励档位（JSON）" required>
          <el-input
            v-model="rewardsText"
            type="textarea"
            :rows="10"
            placeholder='[{"day":1,"amount":"1.00"},{"day":7,"amount":"5.00"}]'
          />
          <div style="width: 100%; font-size: 12px; color: #909399; margin-top: 4px">
            数组元素：day 连续签到天数（≥ 1），amount 奖励金额
          </div>
        </el-form-item>
        <el-form-item v-if="parsedRewards.ok && parsedRewards.rows.length > 0" label="解析预览">
          <el-table :data="parsedRewards.rows" size="small" style="width: 360px">
            <el-table-column prop="day" label="连续天数" width="120" />
            <el-table-column label="奖励金额">
              <template #default="{ row }">¥{{ money((row as RewardItemVM).amount) }}</template>
            </el-table-column>
          </el-table>
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'marketing:checkin:config'" type="primary" native-type="submit">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">签到记录</h3>
      <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onSearch" />

      <DataTable :data="rows" :loading="listLoading" empty-text="暂无签到记录">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="用户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRecord(row).user_id }} {{ asRecord(row).nickname ? `（${asRecord(row).nickname}）` : '' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号" width="140">
          <template #default="{ row }">{{ asRecord(row).mobile || '-' }}</template>
        </el-table-column>
        <el-table-column prop="checkin_date" label="签到日期" width="120" />
        <el-table-column prop="successions" label="连续天数" width="100" />
        <el-table-column label="奖励类型" width="110">
          <template #default="{ row }">{{ rewardTypeText(asRecord(row).reward_type) }}</template>
        </el-table-column>
        <el-table-column label="发放状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="rewardStatusMeta(asRecord(row).reward_status).type">
              {{ rewardStatusMeta(asRecord(row).reward_status).label }}
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
      title="修改签到配置"
      message="修改将影响全部 C 端用户的签到奖励发放，请确认 JSON 配置无误；操作需管理员密码验证并写入审计日志。"
      :need-reason="false"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
