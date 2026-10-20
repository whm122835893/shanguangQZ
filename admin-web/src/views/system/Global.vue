<script setup lang="ts">
/** 全局参数（契约 §8.14）：通用键值对动态编辑表格，读取回显 → 修改 → 高风险确认 → 更新 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getGlobalConfig, updateGlobalConfig } from '@/api/system'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

type KvRow = {
  key: string
  value: string
  locked: boolean
}

/** 已知键 → 中文说明（其余动态键直接展示 key） */
const KEY_LABELS: Record<string, string> = {
  site_name: '站点名称',
  service_phone: '客服电话',
  service_wechat: '客服微信',
  icp_no: '备案号',
  iapp: 'IAPP 备案号',
  service_time: '客服服务时间',
  official_site: '官网地址',
}

/** 后端/请求通道注入键，不作为业务参数展示与提交 */
const SYSTEM_KEYS = ['password', 'reason', 'updated_at', 'updated_by']

const rows = ref<KvRow[]>([])
const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)
const newRow = reactive<{ key: string; value: string }>({ key: '', value: '' })

function toStr(v: unknown): string {
  if (v === null || v === undefined) return ''
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function keyLabel(key: string): string {
  return KEY_LABELS[key] ?? key
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getGlobalConfig()
    rows.value = Object.entries(data)
      .filter(([k]) => !SYSTEM_KEYS.includes(k))
      .map(([k, v]) => ({ key: k, value: toStr(v), locked: k in KEY_LABELS }))
  } finally {
    loading.value = false
  }
}

function onAddRow(): void {
  const key = newRow.key.trim()
  if (key === '') {
    ElMessage.error('请输入参数键名')
    return
  }
  if (SYSTEM_KEYS.includes(key)) {
    ElMessage.error('该键名为系统保留键，不可使用')
    return
  }
  if (rows.value.some((r) => r.key === key)) {
    ElMessage.error(`参数键「${key}」已存在`)
    return
  }
  rows.value.push({ key, value: newRow.value, locked: key in KEY_LABELS })
  newRow.key = ''
  newRow.value = ''
}

function onRemoveRow(idx: number): void {
  rows.value.splice(idx, 1)
}

function openSave(): void {
  if (rows.value.some((r) => r.key.trim() === '')) {
    ElMessage.error('存在键名为空的参数行，请修正后再保存')
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  const items: Record<string, unknown> = {}
  for (const r of rows.value) items[r.key.trim()] = r.value

  submitting.value = true
  try {
    await updateGlobalConfig({ items, reason: payload.reason, password: payload.password })
    ElMessage.success('全局参数已更新')
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
    <div v-loading="loading" class="card">
      <h3 style="margin-top: 0">全局参数</h3>
      <el-alert
        title="站点名/客服电话/客服微信/备案号等通用键值对，支持动态增删；保存需管理员密码验证并写入审计日志。"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />

      <el-form inline @submit.prevent="onAddRow">
        <el-form-item label="键名">
          <el-input v-model="newRow.key" placeholder="如 site_name" style="width: 200px" />
        </el-form-item>
        <el-form-item label="值">
          <el-input v-model="newRow.value" placeholder="参数值" style="width: 260px" />
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'system:config'" type="primary" plain @click="onAddRow">添加参数</el-button>
        </el-form-item>
      </el-form>

      <DataTable :data="rows" :loading="loading" empty-text="暂无全局参数">
        <el-table-column label="参数键" width="220">
          <template #default="{ row }">{{ (row as KvRow).key }}</template>
        </el-table-column>
        <el-table-column label="说明" width="160">
          <template #default="{ row }">{{ keyLabel((row as KvRow).key) }}</template>
        </el-table-column>
        <el-table-column label="参数值" min-width="240">
          <template #default="{ row }">
            <el-input v-model="(row as KvRow).value" placeholder="请输入参数值" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ $index }">
            <el-button v-permission="'system:config'" link type="danger" @click="onRemoveRow($index)">删除</el-button>
          </template>
        </el-table-column>
      </DataTable>

      <div style="margin-top: 16px">
        <el-button v-permission="'system:config'" type="primary" @click="openSave">保存全部参数</el-button>
      </div>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="保存全局参数"
      message="将覆盖保存全部全局参数（含新增与删除行），立即对 C 端生效；操作需管理员密码验证并写入审计日志。"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
