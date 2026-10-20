<script setup lang="ts">
/** 库存审计（契约 §8.5）：审计数据描述 + 恒等式可视化（发行总量 = 池 + 配额 + 售出 + 空投 + 销毁） */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { collectibleAudit } from '@/api/collectible'
import { fmtDateTime } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const loading = ref(true)
const audit = ref<Record<string, unknown> | null>(null)

function pickNum(keys: string[]): number {
  const a = audit.value
  if (!a) return 0
  for (const k of keys) {
    const v = a[k]
    if (v !== undefined && v !== null && v !== '') {
      const n = Number(v)
      if (Number.isFinite(n)) return n
    }
  }
  return 0
}

function pickStr(keys: string[]): string {
  const a = audit.value
  if (!a) return '-'
  for (const k of keys) {
    const v = a[k]
    if (v !== undefined && v !== null && v !== '') {
      if (k.endsWith('_at')) return fmtDateTime(String(v))
      return String(v)
    }
  }
  return '-'
}

/** is_consistent：true 绿色通过，否则红色警示 */
const consistent = computed(() => {
  const a = audit.value
  if (!a) return false
  const v = a['is_consistent']
  return v === true || v === 1 || v === '1' || v === 'true'
})

const edition = computed(() => pickNum(['edition', 'edition_total', 'total_edition']))

interface AuditPart {
  label: string
  value: number
  color: string
  pct: number
}

/** 恒等式各组成部分及占发行总量的百分比 */
const parts = computed<AuditPart[]>(() => {
  const base: Array<{ label: string; value: number; color: string }> = [
    { label: '库存池', value: pickNum(['stock_pool', 'pool']), color: '#409eff' },
    { label: '已配置配额', value: pickNum(['quota_total', 'quota_allocated', 'quota_count']), color: '#e6a23c' },
    { label: '已售出发售', value: pickNum(['sold_count', 'sold']), color: '#67c23a' },
    { label: '已空投', value: pickNum(['airdrop_count', 'airdropped']), color: '#909399' },
    { label: '已销毁', value: pickNum(['destroy_count', 'destroyed']), color: '#f56c6c' },
  ]
  return base.map((p) => ({
    ...p,
    pct: edition.value > 0 ? Math.min(100, Math.round((p.value / edition.value) * 1000) / 10) : 0,
  }))
})

const sumParts = computed(() => parts.value.reduce((acc, p) => acc + p.value, 0))

onMounted(async () => {
  try {
    audit.value = await collectibleAudit(id)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <el-page-header class="card" content="库存审计" @back="router.push('/collectible')" />

    <!-- 校验结论：is_consistent 为 true 绿色，否则红色警示 -->
    <div v-if="audit" class="card">
      <el-alert
        :type="consistent ? 'success' : 'error'"
        show-icon
        :closable="false"
        :title="
          consistent
            ? `库存恒等式校验一致：发行总量 ${edition} = 各部分合计 ${sumParts}`
            : `库存恒等式校验不一致：发行总量 ${edition} ≠ 各部分合计 ${sumParts}，请立即排查库存流水`
        "
      />
    </div>

    <!-- 恒等式可视化 -->
    <div v-if="audit" class="card">
      <h3 style="margin-top: 0">库存构成：发行总量 = 库存池 + 已配置配额 + 已售出 + 已空投 + 已销毁</h3>
      <div class="eq-row">
        <div class="eq-card total">
          <span>发行总量</span>
          <b>{{ edition }}</b>
        </div>
        <span class="eq-op">=</span>
        <div v-for="p in parts" :key="p.label" class="eq-card" :style="{ borderColor: p.color }">
          <span>{{ p.label }}</span>
          <b :style="{ color: p.color }">{{ p.value }}</b>
        </div>
      </div>
      <div v-for="p in parts" :key="p.label" class="progress-row">
        <span class="progress-label">{{ p.label }}</span>
        <el-progress class="progress-bar" :percentage="p.pct" :color="p.color" :stroke-width="14" />
        <span class="progress-value">{{ p.value }}</span>
      </div>
    </div>

    <!-- 审计数据 -->
    <div v-if="audit" class="card">
      <h3 style="margin-top: 0">审计数据</h3>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="审计 ID">{{ pickStr(['id']) }}</el-descriptions-item>
        <el-descriptions-item label="藏品 ID">{{ pickStr(['collectible_id']) }}</el-descriptions-item>
        <el-descriptions-item label="发行总量">{{ edition }}</el-descriptions-item>
        <el-descriptions-item label="库存池">{{ pickNum(['stock_pool', 'pool']) }}</el-descriptions-item>
        <el-descriptions-item label="已配置配额">{{ pickNum(['quota_total', 'quota_allocated', 'quota_count']) }}</el-descriptions-item>
        <el-descriptions-item label="已售出发售">{{ pickNum(['sold_count', 'sold']) }}</el-descriptions-item>
        <el-descriptions-item label="已空投">{{ pickNum(['airdrop_count', 'airdropped']) }}</el-descriptions-item>
        <el-descriptions-item label="已销毁">{{ pickNum(['destroy_count', 'destroyed']) }}</el-descriptions-item>
        <el-descriptions-item label="流通量">{{ pickNum(['circulation']) }}</el-descriptions-item>
        <el-descriptions-item label="校验结果">
          <el-tag :type="consistent ? 'success' : 'danger'" size="small">
            {{ consistent ? '一致' : '不一致' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="操作人">{{ pickStr(['operator', 'operator_name', 'admin_name']) }}</el-descriptions-item>
        <el-descriptions-item label="审计时间">{{ pickStr(['checked_at', 'audit_time', 'created_at']) }}</el-descriptions-item>
      </el-descriptions>
    </div>
  </div>
</template>

<style scoped>
.eq-row { display: flex; align-items: stretch; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.eq-card {
  border: 1px solid #dcdfe6;
  border-radius: 6px;
  padding: 10px 16px;
  min-width: 96px;
  text-align: center;
  font-size: 13px;
  color: #606266;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 4px;
}
.eq-card.total { background: #ecf5ff; border-color: #409eff; }
.eq-card b { font-size: 20px; }
.eq-op { align-self: center; font-size: 20px; font-weight: 700; color: #909399; }
.progress-row { display: flex; align-items: center; gap: 12px; padding: 6px 0; }
.progress-label { width: 90px; text-align: right; font-size: 13px; color: #606266; flex-shrink: 0; }
.progress-bar { flex: 1; }
.progress-value { width: 70px; font-size: 13px; color: #303133; font-weight: 600; }
</style>
