<script setup lang="ts">
/** 支付配置（契约 §8.14）：读取回显 → 修改 → 高风险确认（原因+密码）→ 更新 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getPaymentConfig, updatePaymentConfig } from '@/api/system'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

type PaymentForm = {
  wechat_appid: string
  wechat_mch_id: string
  wechat_secret: string
  wechat_gateway: string
  alipay_app_id: string
  alipay_private_key: string
  alipay_public_key: string
  alipay_gateway: string
  notify_url: string
}

const form = reactive<PaymentForm>({
  wechat_appid: '',
  wechat_mch_id: '',
  wechat_secret: '',
  wechat_gateway: '',
  alipay_app_id: '',
  alipay_private_key: '',
  alipay_public_key: '',
  alipay_gateway: '',
  notify_url: '',
})

const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

/** secret 类字段后端已脱敏输出（含 *），原样/留空则不下发（表示不修改） */
function isMasked(v: string): boolean {
  return v.includes('*')
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getPaymentConfig()
    form.wechat_appid = toStr(data['wechat_appid'])
    form.wechat_mch_id = toStr(data['wechat_mch_id'])
    form.wechat_secret = toStr(data['wechat_secret'])
    form.wechat_gateway = toStr(data['wechat_gateway'])
    form.alipay_app_id = toStr(data['alipay_app_id'])
    form.alipay_private_key = toStr(data['alipay_private_key'])
    form.alipay_public_key = toStr(data['alipay_public_key'])
    form.alipay_gateway = toStr(data['alipay_gateway'])
    form.notify_url = toStr(data['notify_url'])
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  if (form.wechat_appid.trim() === '' && form.alipay_app_id.trim() === '') {
    ElMessage.error('微信与支付宝至少需配置一个 appid')
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  const data: Record<string, unknown> = {
    wechat_appid: form.wechat_appid.trim(),
    wechat_mch_id: form.wechat_mch_id.trim(),
    wechat_gateway: form.wechat_gateway.trim(),
    alipay_app_id: form.alipay_app_id.trim(),
    alipay_public_key: form.alipay_public_key.trim(),
    alipay_gateway: form.alipay_gateway.trim(),
    notify_url: form.notify_url.trim(),
    reason: payload.reason,
    password: payload.password,
  }
  if (form.wechat_secret.trim() !== '' && !isMasked(form.wechat_secret)) data['wechat_secret'] = form.wechat_secret.trim()
  if (form.alipay_private_key.trim() !== '' && !isMasked(form.alipay_private_key)) data['alipay_private_key'] = form.alipay_private_key.trim()

  submitting.value = true
  try {
    await updatePaymentConfig(data)
    ElMessage.success('支付配置已更新')
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
      <h3 style="margin-top: 0">支付配置</h3>
      <el-alert
        title="密钥类字段已脱敏展示；留空或保持打码状态表示不修改，仅在需要更换密钥时输入新值。"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="150px" @submit.prevent="openSave">
        <el-divider content-position="left">微信支付</el-divider>
        <el-form-item label="AppID">
          <el-input v-model="form.wechat_appid" placeholder="微信支付 AppID" />
        </el-form-item>
        <el-form-item label="商户号">
          <el-input v-model="form.wechat_mch_id" placeholder="微信支付商户号" />
        </el-form-item>
        <el-form-item label="API 密钥">
          <el-input v-model="form.wechat_secret" type="password" show-password placeholder="已脱敏，留空表示不修改" />
        </el-form-item>
        <el-form-item label="网关地址">
          <el-input v-model="form.wechat_gateway" placeholder="如 https://api.mch.weixin.qq.com" />
        </el-form-item>

        <el-divider content-position="left">支付宝</el-divider>
        <el-form-item label="AppID">
          <el-input v-model="form.alipay_app_id" placeholder="支付宝应用 AppID" />
        </el-form-item>
        <el-form-item label="应用私钥">
          <el-input v-model="form.alipay_private_key" type="password" show-password placeholder="已脱敏，留空表示不修改" />
        </el-form-item>
        <el-form-item label="支付宝公钥">
          <el-input v-model="form.alipay_public_key" type="textarea" :rows="2" placeholder="用于验签的支付宝公钥" />
        </el-form-item>
        <el-form-item label="网关地址">
          <el-input v-model="form.alipay_gateway" placeholder="如 https://openapi.alipay.com/gateway.do" />
        </el-form-item>

        <el-divider content-position="left">回调</el-divider>
        <el-form-item label="异步通知地址">
          <el-input v-model="form.notify_url" placeholder="支付结果异步通知 URL" />
        </el-form-item>

        <el-form-item>
          <el-button v-permission="'system:config'" type="primary" @click="openSave">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="修改支付配置"
      message="修改支付配置将直接影响线上支付收款，请确认各项参数无误；操作需管理员密码验证并写入审计日志。"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
