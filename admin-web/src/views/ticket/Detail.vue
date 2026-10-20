<script setup lang="ts">
/** 工单详情（契约 §8.17.2/195/196/199）：详情 + 对话时间线 + 回复 + 状态流转 + 快捷补偿（真实资金动作，需密码） */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { ticketDetail, replyTicket, updateTicketStatus, compensateTicket } from '@/api/ticket'
import { fmtDateTime } from '@/utils/date'
import { money, moneyGt } from '@/utils/decimal'

type ReplyRow = {
  id: number
  sender_type: number
  sender_id: number
  sender_name: string
  content: string
  is_internal: number
  time: string
}

type TicketData = {
  id: number
  ticket_no: string
  user_id: number
  user_phone: string
  ticket_type: number
  priority: number
  title: string
  description: string
  status: number
  status_text: string
  assignee_id: number
  assignee_name: string
  solved_at: string
  closed_at: string
  time: string
  replies: ReplyRow[]
}

/** ticket_type：1=支付异常 2=藏品丢失 3=盲盒问题 4=转赠纠纷 5=账号问题 6=其他 */
const TYPE_MAP: Record<number, string> = {
  1: '支付异常',
  2: '藏品丢失',
  3: '盲盒问题',
  4: '转赠纠纷',
  5: '账号问题',
  6: '其他',
}

/** priority：1=紧急 2=高 3=中 4=低 */
const PRIORITY_MAP: Record<number, { label: string; tag: 'danger' | 'warning' | 'primary' | 'info' }> = {
  1: { label: '紧急', tag: 'danger' },
  2: { label: '高', tag: 'warning' },
  3: { label: '中', tag: 'primary' },
  4: { label: '低', tag: 'info' },
}

/** status：1=待处理 2=处理中 3=待用户确认 4=已解决 5=已关闭 */
const STATUS_MAP: Record<number, { label: string; tag: 'warning' | 'primary' | 'success' | 'info' }> = {
  1: { label: '待处理', tag: 'warning' },
  2: { label: '处理中', tag: 'primary' },
  3: { label: '待用户确认', tag: 'warning' },
  4: { label: '已解决', tag: 'success' },
  5: { label: '已关闭', tag: 'info' },
}

/** sender_type：1=用户 2=客服 3=系统 */
const SENDER_MAP: Record<number, { label: string; tag: 'info' | 'success' | 'warning' }> = {
  1: { label: '用户', tag: 'info' },
  2: { label: '客服', tag: 'success' },
  3: { label: '系统', tag: 'warning' },
}

/** 状态流转：当前状态 → 可流转的下一状态 */
const NEXT_STATUS: Record<number, number[]> = {
  1: [2],
  2: [3, 4],
  3: [2, 4],
  4: [5],
  5: [],
}

const route = useRoute()
const ticketId = Number(route.params.id)

const ticket = ref<TicketData | null>(null)
const loading = ref(false)

const replies = computed<ReplyRow[]>(() => ticket.value?.replies ?? [])
const nextStatuses = computed<number[]>(() => NEXT_STATUS[ticket.value?.status ?? 0] ?? [])

async function fetchDetail(): Promise<void> {
  loading.value = true
  try {
    ticket.value = (await ticketDetail(ticketId)) as TicketData
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/* ---------- 回复 ---------- */

const replyContent = ref('')
const replyLoading = ref(false)

async function submitReply(): Promise<void> {
  if (replyContent.value.trim() === '') {
    ElMessage.error('请输入回复内容')
    return
  }
  replyLoading.value = true
  try {
    await replyTicket(ticketId, replyContent.value.trim())
    ElMessage.success('回复已发送')
    replyContent.value = ''
    void fetchDetail()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    replyLoading.value = false
  }
}

/* ---------- 状态流转 ---------- */

const statusLoading = ref(false)

async function goStatus(target: number): Promise<void> {
  statusLoading.value = true
  try {
    await updateTicketStatus(ticketId, target)
    ElMessage.success(`工单状态已更新为「${STATUS_MAP[target]?.label ?? target}」`)
    void fetchDetail()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    statusLoading.value = false
  }
}

/* ---------- 快捷补偿：第 1 步填金额/原因 → 第 2 步 ConfirmDialog 验密码（真实资金动作） ---------- */

const compVisible = ref(false)
const compConfirmVisible = ref(false)
const compLoading = ref(false)
const compForm = reactive<{ amount: string; reason: string }>({ amount: '', reason: '' })

const compMessage = computed(() => {
  const t = ticket.value
  if (!t) return ''
  return `将向工单用户（ID ${t.user_id}）余额补偿 ¥${money(compForm.amount)}。` +
    '补偿为真实资金动作：余额即时入账并写入资金流水，操作不可逆，请谨慎执行。'
})

function openComp(): void {
  compForm.amount = ''
  compForm.reason = ''
  compVisible.value = true
}

function onCompNext(): void {
  if (!compForm.amount || !moneyGt(compForm.amount, 0)) {
    ElMessage.error('请输入大于 0 的补偿金额')
    return
  }
  if (compForm.reason.trim() === '') {
    ElMessage.error('请填写补偿原因')
    return
  }
  compVisible.value = false
  compConfirmVisible.value = true
}

async function onCompConfirm(payload: { reason: string; password: string }): Promise<void> {
  compLoading.value = true
  try {
    await compensateTicket(ticketId, money(compForm.amount), compForm.reason.trim(), payload.password)
    ElMessage.success(`补偿已执行，¥${money(compForm.amount)} 已入用户余额`)
    compConfirmVisible.value = false
    void fetchDetail()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    compLoading.value = false
  }
}

onMounted(() => {
  void fetchDetail()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="detail-header">
        <div class="header-left">
          <span class="ticket-title">{{ ticket?.title ?? '工单详情' }}</span>
          <el-tag v-if="ticket" :type="STATUS_MAP[ticket.status]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[ticket.status]?.label ?? '未知' }}
          </el-tag>
        </div>
        <div class="header-actions">
          <el-button
            v-for="s in nextStatuses"
            :key="s"
            v-permission="'ticket:manage'"
            size="small"
            :type="s === 5 ? 'danger' : 'primary'"
            plain
            :loading="statusLoading"
            @click="goStatus(s)"
          >流转至「{{ STATUS_MAP[s]?.label }}」</el-button>
          <el-button v-permission="'ticket:compensate'" size="small" type="danger" @click="openComp">补偿</el-button>
        </div>
      </div>

      <el-descriptions v-if="ticket" :column="3" border style="margin-top: 16px">
        <el-descriptions-item label="工单号">{{ ticket.ticket_no }}</el-descriptions-item>
        <el-descriptions-item label="用户ID">{{ ticket.user_id }}</el-descriptions-item>
        <el-descriptions-item label="用户手机号">{{ ticket.user_phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="分类">{{ TYPE_MAP[ticket.ticket_type] ?? '未知' }}</el-descriptions-item>
        <el-descriptions-item label="优先级">
          <el-tag :type="PRIORITY_MAP[ticket.priority]?.tag ?? 'info'" size="small">
            {{ PRIORITY_MAP[ticket.priority]?.label ?? '未知' }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="客服">{{ ticket.assignee_name ? `${ticket.assignee_name}（ID ${ticket.assignee_id}）` : '未分配' }}</el-descriptions-item>
        <el-descriptions-item label="问题描述" :span="3">{{ ticket.description || '-' }}</el-descriptions-item>
        <el-descriptions-item label="创建时间">{{ fmtDateTime(ticket.time) }}</el-descriptions-item>
        <el-descriptions-item label="解决时间">{{ ticket.solved_at ? fmtDateTime(ticket.solved_at) : '-' }}</el-descriptions-item>
        <el-descriptions-item label="关闭时间">{{ ticket.closed_at ? fmtDateTime(ticket.closed_at) : '-' }}</el-descriptions-item>
      </el-descriptions>
    </div>

    <div class="card">
      <div class="section-title">对话记录</div>
      <el-empty v-if="replies.length === 0" description="暂无对话" :image-size="80" />
      <el-timeline v-else class="reply-timeline">
        <el-timeline-item
          v-for="r in replies"
          :key="r.id"
          :timestamp="fmtDateTime(r.time)"
          :type="r.sender_type === 2 ? 'success' : undefined"
        >
          <div class="reply-item">
            <div class="reply-meta">
              <el-tag :type="SENDER_MAP[r.sender_type]?.tag ?? 'info'" size="small">
                {{ SENDER_MAP[r.sender_type]?.label ?? '未知' }}
              </el-tag>
              <span class="reply-sender">{{ r.sender_name }}（ID {{ r.sender_id }}）</span>
              <el-tag v-if="r.is_internal === 1" type="warning" size="small" effect="plain">内部备注</el-tag>
            </div>
            <div class="reply-content">{{ r.content }}</div>
          </div>
        </el-timeline-item>
      </el-timeline>

      <div class="reply-box">
        <el-input
          v-model="replyContent"
          type="textarea"
          :rows="4"
          maxlength="1000"
          show-word-limit
          placeholder="输入回复内容，将以客服身份发送给用户"
        />
        <div class="reply-actions">
          <el-button v-permission="'ticket:reply'" type="primary" :loading="replyLoading" @click="submitReply">回复</el-button>
        </div>
      </div>
    </div>

    <el-dialog v-model="compVisible" title="快捷补偿" width="440px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="补偿金额" required>
          <el-input v-model="compForm.amount" placeholder="请输入补偿金额（元）">
            <template #prepend>¥</template>
          </el-input>
        </el-form-item>
        <el-form-item label="补偿原因" required>
          <el-input v-model="compForm.reason" type="textarea" :rows="3" placeholder="请填写补偿原因（将写入资金流水备注与审计日志）" maxlength="200" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="compVisible = false">取消</el-button>
        <el-button type="primary" @click="onCompNext">下一步</el-button>
      </template>
    </el-dialog>

    <ConfirmDialog
      v-model="compConfirmVisible"
      title="确认执行补偿"
      :message="compMessage"
      :need-reason="false"
      :loading="compLoading"
      @confirm="onCompConfirm"
    />
  </div>
</template>

<style scoped>
.detail-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.header-left { display: flex; align-items: center; gap: 10px; }
.ticket-title { font-size: 16px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.section-title { font-weight: 600; margin-bottom: 16px; }
.reply-timeline { padding-left: 4px; }
.reply-item { padding: 4px 0; }
.reply-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.reply-sender { color: #606266; font-size: 12px; }
.reply-content { color: #303133; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
.reply-box { margin-top: 20px; }
.reply-actions { margin-top: 10px; display: flex; justify-content: flex-end; }
</style>
