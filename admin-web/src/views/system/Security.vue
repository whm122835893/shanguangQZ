<script setup lang="ts">
/** 安全策略（契约 §8.14）：登录锁定 / 密码强度 / 2FA 强制等，读取回显 → 修改 → 高风险确认 → 更新 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getSecurityConfig, updateSecurityConfig } from '@/api/system'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

type SecurityForm = {
  login_max_fail: number
  lock_duration: number
  password_min_length: number
  password_complexity: string
  password_expire_days: number
  force_2fa: boolean
  session_timeout: number
}

const form = reactive<SecurityForm>({
  login_max_fail: 5,
  lock_duration: 30,
  password_min_length: 8,
  password_complexity: 'mid',
  password_expire_days: 90,
  force_2fa: false,
  session_timeout: 120,
})

const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)

const COMPLEXITY_OPTIONS = [
  { label: '低（仅长度）', value: 'low' },
  { label: '中（字母+数字）', value: 'mid' },
  { label: '高（大小写+数字+符号）', value: 'high' },
]

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function toNum(v: unknown, fallback: number): number {
  const n = Number(v)
  return Number.isFinite(n) && n > 0 ? n : fallback
}

function toBool(v: unknown): boolean {
  return v === true || v === 1 || v === '1' || v === 'true'
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getSecurityConfig()
    form.login_max_fail = toNum(data['login_max_fail'], 5)
    form.lock_duration = toNum(data['lock_duration'], 30)
    form.password_min_length = toNum(data['password_min_length'], 8)
    const complexity = toStr(data['password_complexity'])
    form.password_complexity = ['low', 'mid', 'high'].includes(complexity) ? complexity : 'mid'
    form.password_expire_days = toNum(data['password_expire_days'], 90)
    form.force_2fa = toBool(data['force_2fa'])
    form.session_timeout = toNum(data['session_timeout'], 120)
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  if (form.password_min_length < 6) {
    ElMessage.error('密码最小长度不得低于 6 位')
    return
  }
  if (form.lock_duration <= 0) {
    ElMessage.error('锁定时长必须大于 0 分钟')
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await updateSecurityConfig({
      login_max_fail: form.login_max_fail,
      lock_duration: form.lock_duration,
      password_min_length: form.password_min_length,
      password_complexity: form.password_complexity,
      password_expire_days: form.password_expire_days,
      force_2fa: form.force_2fa,
      session_timeout: form.session_timeout,
      reason: payload.reason,
      password: payload.password,
    })
    ElMessage.success('安全策略已更新')
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
    <div v-loading="loading" class="card" style="max-width: 620px">
      <h3 style="margin-top: 0">安全策略</h3>
      <el-form label-width="170px" @submit.prevent="openSave">
        <el-divider content-position="left">登录保护</el-divider>
        <el-form-item label="登录失败锁定次数">
          <el-input-number v-model="form.login_max_fail" :min="3" :max="20" :step="1" />
          <span class="tip">连续失败达该次数后锁定账号</span>
        </el-form-item>
        <el-form-item label="锁定时长（分钟）">
          <el-input-number v-model="form.lock_duration" :min="1" :max="1440" :step="5" />
        </el-form-item>
        <el-form-item label="会话超时（分钟）">
          <el-input-number v-model="form.session_timeout" :min="10" :max="1440" :step="10" />
          <span class="tip">后台无操作自动登出时间</span>
        </el-form-item>
        <el-form-item label="强制开启 2FA">
          <el-switch v-model="form.force_2fa" active-text="开启" inactive-text="关闭" />
          <span class="tip">开启后全部管理员必须绑定双因素认证</span>
        </el-form-item>

        <el-divider content-position="left">密码策略</el-divider>
        <el-form-item label="密码最小长度">
          <el-input-number v-model="form.password_min_length" :min="6" :max="32" :step="1" />
        </el-form-item>
        <el-form-item label="密码强度要求">
          <el-select v-model="form.password_complexity" style="width: 240px">
            <el-option v-for="o in COMPLEXITY_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="密码有效期（天）">
          <el-input-number v-model="form.password_expire_days" :min="0" :max="365" :step="15" />
          <span class="tip">0 表示永不过期</span>
        </el-form-item>

        <el-form-item>
          <el-button v-permission="'system:security'" type="primary" @click="openSave">保存策略</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="修改安全策略"
      message="修改安全策略将影响全部管理员的登录与密码校验规则，请确认无误；操作需管理员密码验证并写入审计日志。"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>

<style scoped>
.tip { margin-left: 12px; color: var(--el-text-color-secondary); font-size: 12px; }
</style>
