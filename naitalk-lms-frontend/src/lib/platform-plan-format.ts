export function formatPlanPrice(cents: number, currency: string): string {
  if (cents === 0) return 'Free';
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(cents / 100);
}
