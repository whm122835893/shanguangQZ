import dayjs from 'dayjs'

/** ISO 8601 / 时间戳 → 展示格式（后端统一输出 ISO 8601） */
export function fmtDateTime(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '-'
  return dayjs(value).format('YYYY-MM-DD HH:mm:ss')
}

export function fmtDate(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '-'
  return dayjs(value).format('YYYY-MM-DD')
}

export function fmtRange(start: string | number, end: string | number): string {
  return `${fmtDateTime(start)} ~ ${fmtDateTime(end)}`
}

export { dayjs }
