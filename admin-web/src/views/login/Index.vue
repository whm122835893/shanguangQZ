<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { loginApi } from '@/api/auth'
import { useAdminStore } from '@/store/admin'
import { setToken, setRefreshToken } from '@/utils/token'

const router = useRouter()
const route = useRoute()
const adminStore = useAdminStore()

const form = reactive({ username: '', password: '', totp_code: '' })
const loading = ref(false)
const needTotp = ref(false)
const formRef = ref()

const rules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

async function onLogin(): Promise<void> {
  await formRef.value?.validate()
  loading.value = true
  try {
    const data = await loginApi({
      username: form.username,
      password: form.password,
      totp_code: form.totp_code || undefined,
    })
    setToken(data.token)
    setRefreshToken(data.refresh_token)
    ElMessage.success(`欢迎回来，${data.admin.real_name || data.admin.username}`)
    await adminStore.fetchProfile().catch(() => undefined)
    const redirect = (route.query.redirect as string) || '/'
    void router.replace(redirect)
  } catch (err) {
    const msg = err instanceof Error ? err.message : ''
    if (msg.includes('两步验证')) needTotp.value = true
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <h1 class="title">司南后台管理系统</h1>
      <p class="subtitle">数字藏品运营管理平台</p>
      <el-form ref="formRef" :model="form" :rules="rules" size="large" @submit.prevent="onLogin">
        <el-form-item prop="username">
          <el-input v-model="form.username" placeholder="管理员账号" :prefix-icon="'User'" />
        </el-form-item>
        <el-form-item prop="password">
          <el-input v-model="form.password" type="password" show-password placeholder="密码" :prefix-icon="'Lock'" />
        </el-form-item>
        <el-form-item v-if="needTotp">
          <el-input v-model="form.totp_code" placeholder="两步验证码（6 位）" maxlength="6" :prefix-icon="'Key'" />
        </el-form-item>
        <el-button type="primary" class="submit" :loading="loading" native-type="submit">
          登 录
        </el-button>
      </el-form>
      <p class="hint">连续失败 5 次将锁定 15 分钟</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
.login-page {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1f2d3d 0%, #2b4a6f 55%, #409eff 130%);
}
.login-card {
  width: 380px;
  background: #fff;
  border-radius: 12px;
  padding: 36px 32px 28px;
  box-shadow: 0 12px 40px rgba(0, 21, 41, 0.35);
}
.title { margin: 0; text-align: center; font-size: 22px; color: #303133; }
.subtitle { margin: 8px 0 24px; text-align: center; color: #909399; font-size: 13px; }
.submit { width: 100%; margin-top: 4px; }
.hint { margin: 14px 0 0; text-align: center; font-size: 12px; color: #c0c4cc; }
</style>
