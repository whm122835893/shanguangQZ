/**
 * 脱敏兜底（后端已统一脱敏输出；前端仅对特殊场景做防御性处理）
 * 契约 §8：手机号 199****1234 / 姓名 张*丰 / 证件 110***********234
 */

export function maskPhone(phone: string): string {
  if (!phone || phone.length < 7) return phone || '-'
  return phone.slice(0, 3) + '****' + phone.slice(-4)
}

export function maskName(name: string): string {
  if (!name) return '-'
  if (name.length <= 1) return name
  if (name.length === 2) return name[0] + '*'
  return name[0] + '*'.repeat(name.length - 2) + name[name.length - 1]
}

export function maskIdCard(idCard: string): string {
  if (!idCard || idCard.length < 8) return idCard || '-'
  return idCard.slice(0, 3) + '*'.repeat(Math.max(idCard.length - 6, 4)) + idCard.slice(-3)
}
