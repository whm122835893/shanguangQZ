import Decimal from 'decimal.js'

/** 金额一律 Decimal 处理，toFixed(2)（UI/UX 约束 §11.10） */
export function money(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '0.00'
  try {
    return new Decimal(String(value)).toFixed(2)
  } catch {
    return '0.00'
  }
}

export function moneyAdd(...values: (string | number)[]): string {
  return values.reduce<Decimal>((acc, v) => acc.plus(new Decimal(String(v || 0))), new Decimal(0)).toFixed(2)
}

export function moneySub(a: string | number, b: string | number): string {
  return new Decimal(String(a || 0)).minus(new Decimal(String(b || 0))).toFixed(2)
}

export function moneyMul(a: string | number, b: string | number): string {
  return new Decimal(String(a || 0)).times(new Decimal(String(b || 0))).toFixed(2)
}

export function moneyGt(a: string | number, b: string | number): boolean {
  return new Decimal(String(a || 0)).greaterThan(new Decimal(String(b || 0)))
}
