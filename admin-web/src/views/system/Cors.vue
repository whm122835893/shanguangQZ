<script setup lang="ts">
/** CORS/域名配置（契约 §8.14）：allowed_origins 多行文本等，读取回显 → 修改 → 高风险确认 → 更新 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getCorsConfig, updateCorsConfig } from '@/api/system'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

type CorsForm = {
  allowed_origins: string
  allowed_headers: string
  allowed_methods: string[]
  allow_credentials: boolean
  max_age: number
}

const form = reactive<CorsForm>({
  allowed_origins: '',
  allowed_headers: '',
  allowed_methods: ['GET', 'POST'],
  allow_credentials: true,
  max_age: 3600,
})

const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)

const METHOD_OPTIONS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'].map((m) => ({ label: m, value: m }))

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function toBool(v: unknown): boolean {
  return v === true || v === 1 || v === '1' || v === 'true'
}

function toStringArray(v: unknown): string[] {
  if (Array.isArray(v)) return v.map((x) => String(x)).filter((s) => s !== '')
  return []
}

/** 后端数组/逗号串 → 多行文本（每行一条） */
function toLines(v: unknown): string {
  const arr = Array.isArray(v) ? toStringArray(v) : toStr(v).split(',').map((s) => s.trim()).filter((s) => s !== '')
  return arr.join('\n')
}

function linesToArray(text: string): string[] {
  return text.split('\n').map((s) => s.trim()).filter((s) => s !== '')
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getCorsConfig()
    form.allowed_origins = toLines(data['allowed_origins'])
    form.allowed_headers = toLines(data['allowed_headers'])
    form.allowed_methods = toStringArray(data['allowed_methods'])
    if (form.allowed_methods.length === 0) form.allowed_methods = ['GET', 'POST']
    form.allow_credentials = toBool(data['allow_credentials'])
    const maxAge = Number(data['max_age'])
    form.max_age = Number.isFinite(maxAge) && maxAge > 0 ? maxAge : 3600
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  const origins = linesToArray(form.allowed_origins)
  if (origins.length === 0) {
    ElMessage.error('请至少配置一个允许的来源域名')
    return
  }
  const bad = origins.find((o) => !/^https?:\/\/[\w.-]+(:\d+)?$/.test(o) && o !== '*')
  if (bad) {
    ElMessage.error(`来源格式不正确：${bad}（应为 https://域名[:端口] 或 *）`)
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await updateCorsConfig({
      allowed_origins: linesToArray(form.allowed_origins),
      allowed_headers: linesToArray(form.allowed_headers).map((s) => s.toLowerCase()),
      allowed_methods: form.allowed_methods,
      allow_credentials: form.allow_credentials,
      max_age: form.max_age,
      reason: payload.reason,
      password: payload.password,
    })
    ElMessage.success('CORS 配置已更新')
    dialogVisible.value = false
    await load()
  } catch {
    /* request 拦截器已统一提示，弹窗保持打开便于修正 */
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div v-loading="loading" class="card" style="max-width: 640px">
      <h3 style="margin-top: 0">CORS / 域名配置</h3>
      <el-form label-width="150px" @submit.prevent="openSave">
        <el-form-item label="允许的来源">
          <el-input
            v-model="form.allowed_origins"
            type="textarea"
            :rows="5"
            placeholder="每行一个来源，如 https://www.example.com；通配填 *"
          />
          <div class="tip">每行一个 origin，支持 * 通配全部来源</div>
        </el-form-item>
        <el-form-item label="允许的请求头">
          <el-input
            v-model="form.allowed_headers"
            type="textarea"
            :rows="3"
            placeholder="每行一个请求头，如 Authorization、Content-Type"
          />
        </el-form-item>
        <el-form-item label="允许的方法">
          <el-checkbox-group v-model="form.allowed_methods">
            <el-checkbox v-for="m in METHOD_OPTIONS" :key="m.value" :value="m.value" :label="m.value">{{ m.label }}</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="携带凭据">
          <el-switch v-model="form.allow_credentials" />
          <span class="tip">开启后前端请求可携带 Cookie</span>
        </el-form-item>
        <el-form-item label="预检缓存（秒）">
          <el-input-number v-model="form.max_age" :min="60" :max="86400" :step="60" />
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'system:config'" type="primary" @click="openSave">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="修改 CORS 配置"
      message="修改将影响 C 端/开放接口的跨域访问范围，配置过宽可能带来安全风险；操作需管理员密码验证并写入审计日志。"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>

<style scoped>
.tip { margin-left: 12px; color: var(--el-text-color-secondary); font-size: 12px; }
</style>
