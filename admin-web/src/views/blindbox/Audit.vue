<script setup lang="ts">
/** 盲盒库存审计（契约 §8.6 audit）：恒等式统计卡 + is_consistent 着色 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { blindboxAudit } from '@/api/blindbox'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

interface AuditData {
  edition: number
  stock_pool: number
  sold: number
  airdropped_count: number
  destroyed_count: number
  user_holding: number
  opened_count: number
  diff: number
  is_consistent: boolean
}

const loading = ref(true)
const audit = ref<AuditData | null>(null)

const cards = computed(() => {
  const a = audit.value
  if (!a) return []
  return [
    { label: '发行总量', value: a.edition, bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
    { label: '库存池', value: a.stock_pool, bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
    { label: '已售出发售', value: a.sold, bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
    { label: '已独立空投', value: a.airdropped_count, bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
    { label: '已销毁', value: a.destroyed_count, bg: 'linear-gradient(135deg,#c45656,#e68b8b)' },
    { label: '用户持有', value: a.user_holding, bg: 'linear-gradient(135deg,#337ecc,#5dafbb)' },
    { label: '已开启', value: a.opened_count, bg: 'linear-gradient(135deg,#b88230,#daa154)' },
  ]
})

/** diff = (user_holding + opened) - (sold + airdropped)：>0 数据矛盾；≤0 在途未开启（正常） */
const diffHint = computed(() => {
  const a = audit.value
  if (!a) return ''
  if (a.diff > 0) return `开启数超过发出数 ${a.diff} 个，数据矛盾，请立即核查`
  if (a.diff < 0) return `${-a.diff} 个盲盒已发出未开启（在途，正常业务态）`
  return '全部发出盲盒已开启闭环'
})

async function fetchAudit(): Promise<void> {
  loading.value = true
  try {
    const d = await blindboxAudit(id)
    audit.value = {
      edition: num(d.edition),
      stock_pool: num(d.stock_pool),
      sold: num(d.sold),
      airdropped_count: num(d.airdropped_count),
      destroyed_count: num(d.destroyed_count),
      user_holding: num(d.user_holding),
      opened_count: num(d.opened_count),
      diff: num(d.diff),
      is_consistent: d.is_consistent === true,
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void fetchAudit()
})
</script>

<template>
  <div v-loading="loading">
    <!-- 一致性横幅 -->
    <div class="card">
      <div class="consist-bar">
        <template v-if="audit">
          <el-tag :type="audit.is_consistent ? 'success' : 'danger'" effect="dark" size="large">
            {{ audit.is_consistent ? '账实一致' : '数据矛盾' }}
          </el-tag>
          <span :style="audit.is_consistent ? 'color:#67c23a' : 'color:#f56c6c'">diff = {{ audit.diff }}</span>
          <span class="consist-hint">{{ diffHint }}</span>
        </template>
      </div>
      <div class="formula">
        恒等式：盲盒发行总量(edition) = 库存池(stock_pool) + 已售出发售(sold) + 已独立空投(airdropped) + 已销毁(destroyed)；
        diff = (用户持有 + 已开启) − (已售 + 已空投)
      </div>
    </div>

    <!-- 恒等式统计卡 -->
    <el-row :gutter="16">
      <el-col v-for="c in cards" :key="c.label" :xs="12" :sm="8" :lg="6">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 明细 -->
    <div class="card" style="margin-top: 16px">
      <h3 class="page-title">审计明细</h3>
      <el-descriptions v-if="audit" :column="3" border>
        <el-descriptions-item label="发行总量 edition">{{ audit.edition }}</el-descriptions-item>
        <el-descriptions-item label="库存池 stock_pool">{{ audit.stock_pool }}</el-descriptions-item>
        <el-descriptions-item label="已售出发售 sold">{{ audit.sold }}</el-descriptions-item>
        <el-descriptions-item label="已独立空投 airdropped">{{ audit.airdropped_count }}</el-descriptions-item>
        <el-descriptions-item label="已销毁 destroyed">{{ audit.destroyed_count }}</el-descriptions-item>
        <el-descriptions-item label="用户持有 user_holding">{{ audit.user_holding }}</el-descriptions-item>
        <el-descriptions-item label="已开启 opened">{{ audit.opened_count }}</el-descriptions-item>
        <el-descriptions-item label="diff">{{ audit.diff }}</el-descriptions-item>
        <el-descriptions-item label="一致性">
          <el-tag :type="audit.is_consistent ? 'success' : 'danger'" size="small">
            {{ audit.is_consistent ? '一致' : '矛盾' }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>
      <div style="margin-top: 12px">
        <el-button @click="() => fetchAudit()">刷新</el-button>
        <el-button @click="router.push(`/blindbox/${id}`)">返回详情</el-button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 16px; font-size: 16px; }
.consist-bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.consist-hint { font-size: 13px; color: #606266; }
.formula { margin-top: 10px; font-size: 12px; color: #909399; }
</style>
