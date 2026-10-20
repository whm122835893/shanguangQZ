<script setup lang="ts">
/** 注册福利配置（契约 §8.11.7，125~126）：读取回显 + JSON 编辑保存 */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getRegisterReward, updateRegisterReward } from '@/api/marketing'

const configText = ref('{}')
const loading = ref(false)
const saving = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getRegisterReward()
    configText.value = JSON.stringify(data, null, 2)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

async function onSave(): Promise<void> {
  let parsed: unknown
  try {
    parsed = JSON.parse(configText.value)
  } catch {
    ElMessage.error('JSON 解析失败，请检查格式')
    return
  }
  if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
    ElMessage.error('配置需为 JSON 对象（含 is_enabled / reward_type / reward_config）')
    return
  }
  const rec = parsed as Record<string, unknown>
  const rewardType = String(rec.reward_type ?? '')
  if (rewardType === '') {
    ElMessage.error('reward_type 不能为空（如 balance）')
    return
  }
  if (!Array.isArray(rec.reward_config)) {
    ElMessage.error('reward_config 需为数组')
    return
  }
  saving.value = true
  try {
    await updateRegisterReward({
      is_enabled: Number(rec.is_enabled) === 1 ? 1 : 0,
      reward_type: rewardType,
      reward_config: rec.reward_config,
    })
    ElMessage.success('注册福利配置已更新')
    await load()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-loading="loading" class="card" style="max-width: 760px">
    <h3 style="margin-top: 0">注册福利配置</h3>
    <el-alert
      type="info"
      :closable="false"
      show-icon
      title='JSON 字段说明：is_enabled（1 启用 / 0 停用）、reward_type（奖励类型，如 balance）、reward_config（奖励内容数组）'
      style="margin-bottom: 16px"
    />
    <el-input
      v-model="configText"
      type="textarea"
      :rows="14"
      placeholder='{"is_enabled":1,"reward_type":"balance","reward_config":[]}'
    />
    <div style="margin-top: 12px">
      <el-button v-permission="'marketing:register:config'" type="primary" :loading="saving" @click="onSave">
        保存配置
      </el-button>
      <el-button @click="load">重 置</el-button>
    </div>
  </div>
</template>
