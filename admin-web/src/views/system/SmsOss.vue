<script setup lang="ts">
/** 短信/OSS 配置（契约 §8.14）：服务商/签名/模板/Bucket 等，secret 已脱敏，读取回显 → 修改 → 高风险确认 → 更新 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getSmsOssConfig, updateSmsOssConfig } from '@/api/system'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

type SmsOssForm = {
  sms_provider: string
  sms_sign: string
  sms_template_id: string
  sms_access_key: string
  sms_secret: string
  oss_provider: string
  oss_bucket: string
  oss_endpoint: string
  oss_access_key: string
  oss_secret: string
  oss_domain: string
}

const form = reactive<SmsOssForm>({
  sms_provider: 'aliyun',
  sms_sign: '',
  sms_template_id: '',
  sms_access_key: '',
  sms_secret: '',
  oss_provider: 'aliyun',
  oss_bucket: '',
  oss_endpoint: '',
  oss_access_key: '',
  oss_secret: '',
  oss_domain: '',
})

const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)

const SMS_PROVIDERS = [
  { label: '阿里云短信', value: 'aliyun' },
  { label: '腾讯云短信', value: 'tencent' },
]
const OSS_PROVIDERS = [
  { label: '阿里云 OSS', value: 'aliyun' },
  { label: '腾讯云 COS', value: 'tencent' },
  { label: '七牛云', value: 'qiniu' },
]

/** secret 类字段（后端已脱敏输出），原样/留空则不下发 */
function isMasked(v: string): boolean {
  return v.includes('*')
}

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getSmsOssConfig()
    form.sms_provider = toStr(data['sms_provider']) || 'aliyun'
    form.sms_sign = toStr(data['sms_sign'])
    form.sms_template_id = toStr(data['sms_template_id'])
    form.sms_access_key = toStr(data['sms_access_key'])
    form.sms_secret = toStr(data['sms_secret'])
    form.oss_provider = toStr(data['oss_provider']) || 'aliyun'
    form.oss_bucket = toStr(data['oss_bucket'])
    form.oss_endpoint = toStr(data['oss_endpoint'])
    form.oss_access_key = toStr(data['oss_access_key'])
    form.oss_secret = toStr(data['oss_secret'])
    form.oss_domain = toStr(data['oss_domain'])
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  if (form.sms_sign.trim() === '') {
    ElMessage.error('请填写短信签名')
    return
  }
  if (form.oss_bucket.trim() === '' || form.oss_endpoint.trim() === '') {
    ElMessage.error('请填写 OSS Bucket 与 Endpoint')
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  const data: Record<string, unknown> = {
    sms_provider: form.sms_provider,
    sms_sign: form.sms_sign.trim(),
    sms_template_id: form.sms_template_id.trim(),
    sms_access_key: form.sms_access_key.trim(),
    oss_provider: form.oss_provider,
    oss_bucket: form.oss_bucket.trim(),
    oss_endpoint: form.oss_endpoint.trim(),
    oss_access_key: form.oss_access_key.trim(),
    oss_domain: form.oss_domain.trim(),
    reason: payload.reason,
    password: payload.password,
  }
  if (form.sms_secret.trim() !== '' && !isMasked(form.sms_secret)) data['sms_secret'] = form.sms_secret.trim()
  if (form.oss_secret.trim() !== '' && !isMasked(form.oss_secret)) data['oss_secret'] = form.oss_secret.trim()

  submitting.value = true
  try {
    await updateSmsOssConfig(data)
    ElMessage.success('短信/OSS 配置已更新')
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
      <h3 style="margin-top: 0">短信 / OSS 配置</h3>
      <el-alert
        title="AccessKey Secret 等密钥字段已脱敏展示；留空或保持打码状态表示不修改，仅在需要更换密钥时输入新值。"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="150px" @submit.prevent="openSave">
        <el-divider content-position="left">短信服务</el-divider>
        <el-form-item label="服务商">
          <el-select v-model="form.sms_provider" style="width: 240px">
            <el-option v-for="o in SMS_PROVIDERS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="短信签名">
          <el-input v-model="form.sms_sign" placeholder="如【山海数藏】" />
        </el-form-item>
        <el-form-item label="模板 ID">
          <el-input v-model="form.sms_template_id" placeholder="验证码短信模板 ID" />
        </el-form-item>
        <el-form-item label="AccessKey">
          <el-input v-model="form.sms_access_key" placeholder="云账号 AccessKey ID" />
        </el-form-item>
        <el-form-item label="AccessKey Secret">
          <el-input v-model="form.sms_secret" type="password" show-password placeholder="已脱敏，留空表示不修改" />
        </el-form-item>

        <el-divider content-position="left">对象存储（OSS）</el-divider>
        <el-form-item label="服务商">
          <el-select v-model="form.oss_provider" style="width: 240px">
            <el-option v-for="o in OSS_PROVIDERS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="Bucket">
          <el-input v-model="form.oss_bucket" placeholder="存储桶名称" />
        </el-form-item>
        <el-form-item label="Endpoint">
          <el-input v-model="form.oss_endpoint" placeholder="如 https://oss-cn-hangzhou.aliyuncs.com" />
        </el-form-item>
        <el-form-item label="AccessKey">
          <el-input v-model="form.oss_access_key" placeholder="云账号 AccessKey ID" />
        </el-form-item>
        <el-form-item label="AccessKey Secret">
          <el-input v-model="form.oss_secret" type="password" show-password placeholder="已脱敏，留空表示不修改" />
        </el-form-item>
        <el-form-item label="绑定域名">
          <el-input v-model="form.oss_domain" placeholder="CDN 加速域名（可选）" />
        </el-form-item>

        <el-form-item>
          <el-button v-permission="'system:config'" type="primary" @click="openSave">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="修改短信/OSS 配置"
      message="修改将影响验证码短信发送与文件存储服务，请确认各项参数无误；操作需管理员密码验证并写入审计日志。"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
