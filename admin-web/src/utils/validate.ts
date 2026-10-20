/** 表单校验规则（UI/UX 约束 §11.19：所有表单 rules 校验） */
import type { FormItemRule } from 'element-plus'

export const required = (message: string): FormItemRule => ({ required: true, message, trigger: 'blur' })

export const requiredChange = (message: string): FormItemRule => ({ required: true, message, trigger: 'change' })

export const mobile: FormItemRule = {
  pattern: /^1[3-9]\d{9}$/,
  message: '手机号格式不正确',
  trigger: 'blur',
}

export const amount = (requiredMsg: string): FormItemRule[] => [
  { required: true, message: requiredMsg, trigger: 'blur' },
  {
    validator: (_rule, value: string, callback: (err?: Error) => void) => {
      if (value === '' || value === undefined) return callback()
      if (!/^\d+(\.\d{1,2})?$/.test(String(value))) return callback(new Error('金额格式不正确（最多两位小数）'))
      if (Number(value) <= 0) return callback(new Error('金额必须大于 0'))
      callback()
    },
    trigger: 'blur',
  },
]

export const positiveInt = (msg: string): FormItemRule => ({
  pattern: /^[1-9]\d*$/,
  message: msg,
  trigger: 'blur',
})

export const percentRange = (): FormItemRule => ({
  validator: (_rule, value: number, callback: (err?: Error) => void) => {
    if (value === undefined || value === null) return callback()
    if (value <= 0 || value > 100) return callback(new Error('概率范围 (0, 100]'))
    callback()
  },
  trigger: 'change',
})

export const passwordStrong: FormItemRule = {
  validator: (_rule, value: string, callback: (err?: Error) => void) => {
    if (!value) return callback(new Error('请输入密码'))
    if (value.length < 8) return callback(new Error('密码长度至少 8 位'))
    callback()
  },
  trigger: 'blur',
}
