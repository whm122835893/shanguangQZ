<script setup lang="ts">
/** 发售配置（契约 §8.6 release / relist）
 *  草稿/已下架 → 发售配置（release）；已售罄/已下架 → 重新上架扩容（relist，高风险）
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { blindboxDetail, releaseBlindbox, relistBlindbox } from '@/api/blindbox'
import { money } from '@/utils/decimal'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

const loading = ref(true)
const boxName = ref('')
const saleStatus = ref(0)
const stockPool = ref(0)

const STATUS_MAP: Record<number, string> = { 0: '草稿', 1: '发售中', 2: '已售罄', 3: '已下架' }

const canRelease = computed(() => saleStatus.value === 0 || saleStatus.value === 3)
const canRelist = computed(() => saleStatus.value === 2 || saleStatus.value === 3)

async function fetchDetail(): Promise<void> {
  loading.value = true
  try {
    const d = await blindboxDetail(id)
    boxName.value = String(d.name ?? '')
    saleStatus.value = num(d.sale_status)
    stockPool.value = num(d.stock_pool)
    releaseForm.price = String(d.price ?? '')
    releaseForm.perUserLimit = num(d.per_user_limit) || undefined
  } finally {
    loading.value = false
  }
}

/* ---------- 发售配置 ---------- */
const releaseForm = reactive<{
  price: string
  releaseQuantity: number | undefined
  onsaleAt: string
  offSaleAt: string
  perUserLimit: number | undefined
}>({
  price: '',
  releaseQuantity: undefined,
  onsaleAt: '',
  offSaleAt: '',
  perUserLimit: undefined,
})

const releaseConfirmVisible = ref(false)
const releaseLoading = ref(false)

function submitRelease(): void {
  const price = Number(releaseForm.price)
  if (!Number.isFinite(price) || price <= 0) {
    ElMessage.error('请填写正确的发售价格')
    return
  }
  const qty = Number(releaseForm.releaseQuantity)
  if (!Number.isInteger(qty) || qty <= 0) {
    ElMessage.error('发售数量必须为正整数')
    return
  }
  if (qty > stockPool.value) {
    ElMessage.error(`发售数量不得超过盲盒库存池（${stockPool.value}）`)
    return
  }
  releaseConfirmVisible.value = true
}

async function doRelease(_payload: { reason: string; password: string }): Promise<void> {
  releaseLoading.value = true
  try {
    await releaseBlindbox(id, {
      price: releaseForm.price,
      release_quantity: Number(releaseForm.releaseQuantity),
      onsale_at: releaseForm.onsaleAt,
      off_sale_at: releaseForm.offSaleAt,
      per_user_limit: Number(releaseForm.perUserLimit ?? 0),
    })
    ElMessage.success('发售成功，盲盒已进入发售中')
    releaseConfirmVisible.value = false
    void router.push(`/blindbox/${id}`)
  } finally {
    releaseLoading.value = false
  }
}

/* ---------- 重新上架（扩容，高风险） ---------- */
const relistForm = reactive<{
  releaseQuantity: number | undefined
  price: string
  onsaleAt: string
  offSaleAt: string
  reason: string
}>({
  releaseQuantity: undefined,
  price: '',
  onsaleAt: '',
  offSaleAt: '',
  reason: '',
})

const relistConfirmVisible = ref(false)
const relistLoading = ref(false)

function submitRelist(): void {
  const qty = Number(relistForm.releaseQuantity)
  if (!Number.isInteger(qty) || qty <= 0) {
    ElMessage.error('追加数量必须为正整数')
    return
  }
  if (qty > stockPool.value) {
    ElMessage.error(`追加数量不得超过盲盒库存池（${stockPool.value}）`)
    return
  }
  if (relistForm.price !== '' && (Number.isNaN(Number(relistForm.price)) || Number(relistForm.price) <= 0)) {
    ElMessage.error('价格必须为正数，留空则保持原价')
    return
  }
  relistConfirmVisible.value = true
}

async function doRelist(payload: { reason: string; password: string }): Promise<void> {
  relistLoading.value = true
  try {
    await relistBlindbox(id, {
      release_quantity: Number(relistForm.releaseQuantity),
      price: relistForm.price || undefined,
      onsale_at: relistForm.onsaleAt || undefined,
      off_sale_at: relistForm.offSaleAt || undefined,
      reason: payload.reason,
    })
    ElMessage.success('重新上架成功')
    relistConfirmVisible.value = false
    void router.push(`/blindbox/${id}`)
  } finally {
    relistLoading.value = false
  }
}

onMounted(() => {
  void fetchDetail()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="bar">
        <h3 class="page-title">发售配置</h3>
        <el-tag>{{ boxName }}</el-tag>
        <el-tag :type="canRelease ? 'info' : 'success'">当前状态：{{ STATUS_MAP[saleStatus] ?? '未知' }}</el-tag>
        <el-tag type="warning" effect="plain">盲盒库存池：{{ stockPool }}</el-tag>
      </div>

      <el-alert
        v-if="saleStatus === 1"
        title="盲盒已在发售中，无需配置；如需追加请等待售罄/下架后再重新上架"
        type="info"
        :closable="false"
        show-icon
      />
    </div>

    <!-- 发售配置（草稿/已下架） -->
    <div v-if="canRelease" class="card">
      <h3 class="page-title">配置发售参数</h3>
      <el-form label-width="110px" style="max-width: 560px">
        <el-form-item label="发售价格" required>
          <el-input v-model="releaseForm.price" placeholder="0.00" style="width: 200px">
            <template #append>元</template>
          </el-input>
        </el-form-item>
        <el-form-item label="发售数量" required>
          <el-input-number v-model="releaseForm.releaseQuantity" :min="1" :step="1" :precision="0" style="width: 200px" />
          <span class="form-tip">不超过库存池 {{ stockPool }}</span>
        </el-form-item>
        <el-form-item label="开售时间">
          <el-date-picker
            v-model="releaseForm.onsaleAt"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空则立即开售"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="截止时间">
          <el-date-picker
            v-model="releaseForm.offSaleAt"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空则不限时"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="每人限购">
          <el-input-number v-model="releaseForm.perUserLimit" :min="0" :step="1" :precision="0" style="width: 200px" />
          <span class="form-tip">0 表示不限购</span>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="submitRelease">确认发售</el-button>
          <el-button @click="router.push(`/blindbox/${id}`)">取消</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 重新上架（已售罄/已下架扩容，高风险） -->
    <div v-if="canRelist" class="card">
      <div class="danger-bar">⚠️ 重新上架为高风险操作：追加数量将从库存池扣减并立即进入发售中</div>
      <el-form label-width="110px" style="max-width: 560px">
        <el-form-item label="追加数量" required>
          <el-input-number v-model="relistForm.releaseQuantity" :min="1" :step="1" :precision="0" style="width: 200px" />
          <span class="form-tip">不超过库存池 {{ stockPool }}</span>
        </el-form-item>
        <el-form-item label="价格（元）">
          <el-input v-model="relistForm.price" placeholder="留空保持原价" style="width: 200px" />
        </el-form-item>
        <el-form-item label="开售时间">
          <el-date-picker
            v-model="relistForm.onsaleAt"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空则立即开售"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="截止时间">
          <el-date-picker
            v-model="relistForm.offSaleAt"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空则不限时"
            style="width: 240px"
          />
        </el-form-item>
        <el-form-item label="操作原因" required>
          <el-input v-model="relistForm.reason" type="textarea" :rows="2" maxlength="200" placeholder="将写入审计日志" />
        </el-form-item>
        <el-form-item>
          <el-button type="danger" @click="submitRelist">重新上架</el-button>
          <el-button @click="router.push(`/blindbox/${id}`)">取消</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 高风险确认：发售 -->
    <ConfirmDialog
      v-model="releaseConfirmVisible"
      title="确认发售盲盒"
      :message="`确认以 ¥${money(releaseForm.price)} 发售 ${releaseForm.releaseQuantity ?? 0} 个盲盒？发售状态将不可编辑`"
      :need-reason="false"
      :loading="releaseLoading"
      @confirm="doRelease"
    />

    <!-- 高风险确认：重新上架 -->
    <ConfirmDialog
      v-model="relistConfirmVisible"
      title="确认重新上架"
      :message="`确认追加 ${relistForm.releaseQuantity ?? 0} 个盲盒并重新进入发售中？`"
      :loading="relistLoading"
      @confirm="doRelist"
    />
  </div>
</template>

<style scoped>
.page-title { margin: 0; font-size: 16px; }
.bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.form-tip { font-size: 12px; color: #909399; margin-left: 10px; }
</style>
