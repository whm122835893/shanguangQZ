<script setup lang="ts">
/** 盲盒详情（契约 §8.6 detail）：顶部统计条（UI/UX §11.14）+ el-descriptions + 开启/销毁记录 Tab */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { blindboxDetail, openRecords, blindboxDestroyRecords } from '@/api/blindbox'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

const route = useRoute()
const id = Number(route.params.id)

const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v))
const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

interface BoxInfo {
  name: string
  cover: string
  image: string
  description: string
  edition: number
  price: string
  saleStatus: number
  stockPool: number
  sold: number
  airdropped: number
  destroyed: number
  perUserLimit: number
  openDraw: number
  releaseQuantity: number
  onsaleAt: string
  offSaleAt: string
  probSum: number
  emptyRate: number
  time: string
}

const STATUS_MAP: Record<number, { label: string; tag: 'info' | 'success' | 'warning' | 'danger' }> = {
  0: { label: '草稿', tag: 'info' },
  1: { label: '发售中', tag: 'success' },
  2: { label: '已售罄', tag: 'warning' },
  3: { label: '已下架', tag: 'danger' },
}

const loading = ref(true)
const info = ref<BoxInfo | null>(null)

/** 盲盒流通量 = 已售出发售 + 已独立空投（发出未回收口径） */
const circulation = computed(() => (info.value ? info.value.sold + info.value.airdropped : 0))

/** 顶部统计条（UI/UX §11.14） */
const statCards = computed(() => {
  const d = info.value
  if (!d) return []
  return [
    { label: '盲盒发行总量', value: String(d.edition), bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
    { label: '盲盒已售出发售', value: String(d.sold), bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
    { label: '盲盒库存池', value: String(d.stockPool), bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
    { label: '盲盒流通量', value: String(circulation.value), bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
  ]
})

async function fetchDetail(): Promise<void> {
  const d = await blindboxDetail(id)
  info.value = {
    name: str(d.name),
    cover: str(d.cover),
    image: str(d.image),
    description: str(d.description),
    edition: num(d.edition),
    price: str(d.price),
    saleStatus: num(d.sale_status),
    stockPool: num(d.stock_pool),
    sold: num(d.sold),
    airdropped: num(d.airdropped_count),
    destroyed: num(d.destroyed_count),
    perUserLimit: num(d.per_user_limit),
    openDraw: num(d.open_draw),
    releaseQuantity: num(d.release_quantity),
    onsaleAt: str(d.onsale_at),
    offSaleAt: str(d.off_sale_at),
    probSum: num(d.probability_sum),
    emptyRate: num(d.empty_prize_rate),
    time: str(d.time),
  }
}

/* ---------- 开启记录 ---------- */
const activeTab = ref<'open' | 'destroy'>('open')
const openList = ref<Record<string, unknown>[]>([])
const openTotal = ref(0)
const openPage = ref(1)
const openSize = ref(10)
const openLoading = ref(false)

async function fetchOpenRecords(): Promise<void> {
  openLoading.value = true
  try {
    const data = await openRecords(id, { page: openPage.value, page_size: openSize.value })
    openList.value = data.list
    openTotal.value = data.total
  } finally {
    openLoading.value = false
  }
}

function onOpenPageChange(p: number, s: number): void {
  openPage.value = p
  openSize.value = s
  void fetchOpenRecords()
}

/* ---------- 销毁记录 ---------- */
const destroyList = ref<Record<string, unknown>[]>([])
const destroyTotal = ref(0)
const destroyPage = ref(1)
const destroySize = ref(10)
const destroyLoading = ref(false)

async function fetchDestroyRecords(): Promise<void> {
  destroyLoading.value = true
  try {
    const data = await blindboxDestroyRecords(id, { page: destroyPage.value, page_size: destroySize.value })
    destroyList.value = data.list
    destroyTotal.value = data.total
  } finally {
    destroyLoading.value = false
  }
}

function onDestroyPageChange(p: number, s: number): void {
  destroyPage.value = p
  destroySize.value = s
  void fetchDestroyRecords()
}

onMounted(async () => {
  try {
    await Promise.all([fetchDetail(), fetchOpenRecords(), fetchDestroyRecords()])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <!-- 顶部统计条（UI/UX §11.14） -->
    <el-row :gutter="16">
      <el-col v-for="c in statCards" :key="c.label" :xs="12" :sm="6">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <!-- 基本信息 -->
    <div class="card" style="margin-top: 16px">
      <h3 class="page-title">盲盒详情</h3>
      <template v-if="info">
        <el-descriptions :column="3" border>
          <el-descriptions-item label="名称">{{ info.name }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="STATUS_MAP[info.saleStatus]?.tag ?? 'info'" size="small">
              {{ STATUS_MAP[info.saleStatus]?.label ?? '未知' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="价格">¥{{ money(info.price) }}</el-descriptions-item>
          <el-descriptions-item label="发行总量">{{ info.edition }}</el-descriptions-item>
          <el-descriptions-item label="发售数量">{{ info.releaseQuantity }}</el-descriptions-item>
          <el-descriptions-item label="每人限购">{{ info.perUserLimit > 0 ? `${info.perUserLimit} 个` : '不限' }}</el-descriptions-item>
          <el-descriptions-item label="开盒抽奖">{{ info.openDraw === 1 ? '开启' : '关闭' }}</el-descriptions-item>
          <el-descriptions-item label="概率总和">
            <el-tag :type="info.probSum > 100 ? 'danger' : 'success'" size="small">{{ info.probSum }}%</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="空奖率">{{ info.emptyRate }}%</el-descriptions-item>
          <el-descriptions-item label="发售时间">{{ fmtDateTime(info.onsaleAt) }}</el-descriptions-item>
          <el-descriptions-item label="下售时间">{{ fmtDateTime(info.offSaleAt) }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ fmtDateTime(info.time) }}</el-descriptions-item>
          <el-descriptions-item label="描述" :span="3">{{ info.description || '-' }}</el-descriptions-item>
          <el-descriptions-item label="封面" :span="3">
            <el-image
              v-if="info.image"
              :src="info.image"
              :preview-src-list="[info.image]"
              preview-teleported
              fit="cover"
              style="width: 96px; height: 96px; border-radius: 6px"
            />
            <span v-else>-</span>
          </el-descriptions-item>
        </el-descriptions>
      </template>
    </div>

    <!-- 记录 Tab -->
    <div class="card">
      <el-tabs v-model="activeTab">
        <el-tab-pane label="开启记录" name="open">
          <DataTable :data="openList" :loading="openLoading" empty-text="暂无开启记录">
            <el-table-column label="ID" prop="id" width="80" />
            <el-table-column label="用户" min-width="120">
              <template #default="{ row }">{{ str(row.user_nickname) || `UID ${num(row.user_id)}` }}</template>
            </el-table-column>
            <el-table-column label="手机号" width="130">
              <template #default="{ row }">{{ str(row.user_mobile_masked) || '-' }}</template>
            </el-table-column>
            <el-table-column label="开出藏品" min-width="140">
              <template #default="{ row }">
                <el-tag v-if="row.is_empty_prize === true || num(row.collectible_id) === 0" type="info" size="small">空奖</el-tag>
                <span v-else>{{ str(row.collectible_name) || `#${num(row.collectible_id)}` }}</span>
              </template>
            </el-table-column>
            <el-table-column label="数量" width="80">
              <template #default="{ row }">{{ num(row.quantity) }}</template>
            </el-table-column>
            <el-table-column label="时间" width="170">
              <template #default="{ row }">{{ fmtDateTime(str(row.time)) }}</template>
            </el-table-column>
          </DataTable>
          <Pagination :page="openPage" :page-size="openSize" :total="openTotal" :loading="openLoading" @change="onOpenPageChange" />
        </el-tab-pane>

        <el-tab-pane label="销毁记录" name="destroy">
          <DataTable :data="destroyList" :loading="destroyLoading" empty-text="暂无销毁记录">
            <el-table-column label="ID" prop="id" width="80" />
            <el-table-column label="销毁数量" width="100">
              <template #default="{ row }">{{ num(row.quantity) }}</template>
            </el-table-column>
            <el-table-column label="原因" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">{{ str(row.reason) || '-' }}</template>
            </el-table-column>
            <el-table-column label="操作人" width="120">
              <template #default="{ row }">{{ str(row.admin_name) || '-' }}</template>
            </el-table-column>
            <el-table-column label="时间" width="170">
              <template #default="{ row }">{{ fmtDateTime(str(row.time)) }}</template>
            </el-table-column>
          </DataTable>
          <Pagination :page="destroyPage" :page-size="destroySize" :total="destroyTotal" :loading="destroyLoading" @change="onDestroyPageChange" />
        </el-tab-pane>
      </el-tabs>
    </div>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 16px; font-size: 16px; }
</style>
