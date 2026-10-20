<script setup lang="ts">
/** 自定义导出（契约 §8.18.6）：表选择 + 字段多选（预置 + 自定义）+ 日期范围 + 摘要预览 → CSV 下载 */
import { computed, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { customExport } from '@/api/report'

/** 导出白名单表 + 预置字段（与后端白名单对齐，防注入） */
const TABLES: Array<{ value: string; label: string; fields: string[] }> = [
  { value: 'user', label: '用户表（user）', fields: ['id', 'username', 'nickname', 'mobile', 'money', 'status', 'createtime'] },
  { value: 'user_collection', label: '用户藏品（user_collection）', fields: ['id', 'user_id', 'collection_id', 'num', 'sell_num', 'lock_num', 'createtime'] },
  { value: 'artel_order', label: '订单表（artel_order）', fields: ['id', 'order_sn', 'user_id', 'order_type', 'order_status', 'total_price', 'num', 'pay_channel', 'fee', 'pay_time', 'createtime'] },
  { value: 'recharge_order', label: '充值订单（recharge_order）', fields: ['id', 'order_sn', 'user_id', 'amount', 'status', 'pay_way', 'pay_time', 'createtime'] },
  { value: 'wallet_transaction', label: '资金流水（wallet_transaction）', fields: ['id', 'user_id', 'direction', 'amount', 'balance_before', 'balance_after', 'biz_type', 'createtime'] },
  { value: 'transfer_record', label: '转赠记录（transfer_record）', fields: ['id', 'user_id', 'to_user_id', 'collection_id', 'num', 'status', 'createtime'] },
  { value: 'market_goods', label: '寄售商品（market_goods）', fields: ['id', 'user_id', 'collection_id', 'price', 'status', 'createtime'] },
  { value: 'support_ticket', label: '工单（support_ticket）', fields: ['id', 'ticket_no', 'user_id', 'ticket_type', 'priority', 'status', 'createtime'] },
  { value: 'feedback', label: '用户反馈（feedback）', fields: ['id', 'user_id', 'type', 'status', 'createtime'] },
  { value: 'refund', label: '退款记录（refund）', fields: ['id', 'refund_no', 'user_id', 'amount', 'status', 'createtime'] },
]

const table = ref('')
const checked = ref<string[]>([])
const customInput = ref('')
const range = ref<[string, string] | null>(null)
const exporting = ref(false)

const presetFields = computed<string[]>(() => TABLES.find((t) => t.value === table.value)?.fields ?? [])

function onTableChange(): void {
  checked.value = [...presetFields.value]
}

/** 自定义字段（逗号分隔，仅允许合法字段名字符） */
const customParsed = computed<string[]>(() =>
  customInput.value
    .split(/[,，\s]+/)
    .map((s) => s.trim())
    .filter((s) => /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(s)),
)

/** 最终导出字段：预置勾选 + 自定义，去重保序 */
const finalFields = computed<string[]>(() => {
  const seen = new Set<string>()
  const out: string[] = []
  for (const f of [...checked.value, ...customParsed.value]) {
    if (!seen.has(f)) {
      seen.add(f)
      out.push(f)
    }
  }
  return out
})

const canExport = computed(() => table.value !== '' && finalFields.value.length > 0)

const rangeText = computed(() => (range.value ? `${range.value[0]} ~ ${range.value[1]}` : '全部时间'))

const tableLabel = computed(() => TABLES.find((t) => t.value === table.value)?.label ?? '-')

async function onExport(): Promise<void> {
  if (table.value === '') {
    ElMessage.error('请选择导出表')
    return
  }
  if (finalFields.value.length === 0) {
    ElMessage.error('请至少勾选一个导出字段')
    return
  }
  exporting.value = true
  try {
    const params = range.value ? { date_start: range.value[0], date_end: range.value[1] } : undefined
    await customExport(table.value, finalFields.value, params)
    ElMessage.success('导出成功，CSV 文件已下载')
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <div>
    <div class="card">
      <h3 style="margin-top: 0">导出配置</h3>
      <el-form label-width="90px" @submit.prevent="onExport">
        <el-form-item label="导出表" required>
          <el-select v-model="table" placeholder="请选择导出表" style="width: 320px" @change="onTableChange">
            <el-option v-for="t in TABLES" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围">
          <el-date-picker
            v-model="range"
            type="daterange"
            value-format="YYYY-MM-DD"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            style="width: 320px"
          />
        </el-form-item>
      </el-form>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">导出字段</h3>
      <el-checkbox-group v-model="checked" :disabled="table === ''" style="margin-bottom: 12px">
        <el-checkbox v-for="f in presetFields" :key="f" :label="f">{{ f }}</el-checkbox>
      </el-checkbox-group>
      <el-input
        v-model="customInput"
        :disabled="table === ''"
        placeholder="自定义字段（英文逗号分隔），如 ext_field,remark；留空则仅导出勾选字段"
        style="max-width: 560px"
        clearable
      />
    </div>

    <div class="card">
      <h3 style="margin-top: 0">导出预览</h3>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="导出表">{{ tableLabel }}</el-descriptions-item>
        <el-descriptions-item label="日期范围">{{ rangeText }}</el-descriptions-item>
        <el-descriptions-item label="字段数量">{{ finalFields.length }}</el-descriptions-item>
        <el-descriptions-item label="文件格式">CSV（后端流式下载）</el-descriptions-item>
      </el-descriptions>
      <div class="field-tags">
        <el-tag v-for="f in finalFields" :key="f" size="small" style="margin: 0 8px 8px 0">{{ f }}</el-tag>
        <span v-if="finalFields.length === 0" class="field-empty">请先选择导出表并勾选字段</span>
      </div>
      <el-button
        v-permission="'report:export'"
        type="primary"
        :disabled="!canExport"
        :loading="exporting"
        @click="onExport"
      >
        导出 CSV
      </el-button>
    </div>
  </div>
</template>

<style scoped>
.field-tags { margin: 14px 0 16px; }
.field-empty { color: #909399; font-size: 13px; }
</style>
