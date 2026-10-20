<script setup lang="ts">
/**
 * 网页装修（契约 §8.13.5 /cms/decoration）：site_config(cms.decoration) JSON 配置编辑
 * GET 回显 → textarea 编辑（JSON 格式校验）→ ConfirmDialog 确认 → PUT 全量键覆盖
 */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { getDecoration, updateDecoration } from '@/api/cms'

const text = ref('')
const loading = ref(false)
const saving = ref(false)
const confirmVisible = ref(false)

function jsonError(e: unknown): string {
  return e instanceof Error ? e.message : String(e)
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const cfg = await getDecoration()
    text.value = JSON.stringify(cfg, null, 2)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/** 解析并校验 textarea 内容：必须是合法 JSON 对象 */
function parseConfig(): Record<string, unknown> | null {
  try {
    const parsed: unknown = JSON.parse(text.value)
    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
      ElMessage.error('装修配置必须是 JSON 对象，如 {"site_name":"司南数藏","sections":[]}')
      return null
    }
    return parsed as Record<string, unknown>
  } catch (e) {
    ElMessage.error(`JSON 格式错误：${jsonError(e)}`)
    return null
  }
}

/** 一键格式化（含校验） */
function formatJson(): void {
  try {
    text.value = JSON.stringify(JSON.parse(text.value), null, 2)
    ElMessage.success('已格式化')
  } catch (e) {
    ElMessage.error(`JSON 格式错误：${jsonError(e)}`)
  }
}

function onSave(): void {
  if (parseConfig() === null) return
  confirmVisible.value = true
}

async function onConfirm(_payload: { reason: string; password: string }): Promise<void> {
  const cfg = parseConfig()
  if (cfg === null) {
    confirmVisible.value = false
    return
  }
  saving.value = true
  try {
    await updateDecoration(cfg)
    ElMessage.success('装修配置已保存，前端小程序生效约 1 分钟')
    confirmVisible.value = false
    void load()
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
  <div v-loading="loading" class="card">
    <el-alert
      title="装修配置以 JSON 存储（site_config cms.decoration），保存后全量覆盖对应键；前端小程序生效约 1 分钟"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 12px"
    />

    <div class="toolbar">
      <el-button @click="formatJson">格式化 JSON</el-button>
      <el-button v-permission="'cms:decoration:edit'" type="primary" :disabled="loading" @click="onSave">保存配置</el-button>
    </div>

    <el-input
      v-model="text"
      type="textarea"
      :rows="20"
      class="json-editor"
      placeholder='请输入 JSON 配置，支持键：theme_color / button_color / background_gradient / site_name / site_logo / sections'
      spellcheck="false"
    />

    <div class="key-tip">
      支持字段：theme_color 主题色 · button_color 按钮色 · background_gradient 背景渐变 ·
      site_name 站点名称 · site_logo 站点 LOGO · sections 楼层配置
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="确认保存装修配置"
      message="保存后将覆盖 C 端装修配置（未传键保留原值，已传键全量覆盖），影响小程序端首页展示，请确认 JSON 内容无误。"
      :need-reason="false"
      :loading="saving"
      @confirm="onConfirm"
    />
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: flex-end; gap: 12px; margin-bottom: 12px; }
.json-editor :deep(textarea) { font-family: 'JetBrains Mono', Consolas, Monaco, monospace; font-size: 13px; line-height: 1.6; }
.key-tip { margin-top: 10px; font-size: 12px; color: #909399; }
</style>
