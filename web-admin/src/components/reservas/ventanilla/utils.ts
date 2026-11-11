export const hhmm = (h: string) => h.substring(0, 5);

export function hoyBoliviaISO(): string {
  try {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/La_Paz',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date());
  } catch {
    return new Date().toISOString().substring(0, 10);
  }
}
