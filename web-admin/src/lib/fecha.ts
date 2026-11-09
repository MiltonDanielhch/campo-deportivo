const TZ_BOLIVIA = 'America/La_Paz';

function formatearFechaBolivia(timestamp: number): string {
  // en-CA produce YYYY-MM-DD y respetamos America/La_Paz para el día operativo.
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: TZ_BOLIVIA,
  }).format(new Date(timestamp));
}

/**
 * Fecha ISO de hoy en Bolivia.
 *
 * No usa toISOString() porque eso devolvía fecha UTC y podía dar el día
 * anterior cuando el browser/APP_TIMEZONE no coincidían con Bolivia.
 */
export function hoyISO(): string {
  return formatearFechaBolivia(Date.now());
}

/**
 * Fecha ISO hace N días, en Bolivia.
 */
export function haceNDiasISO(dias: number): string {
  return formatearFechaBolivia(Date.now() - dias * 86_400_000);
}

/**
 * Fecha ISO dentro de N días, en Bolivia.
 */
export function dentroNDiasISO(dias: number): string {
  return formatearFechaBolivia(Date.now() + dias * 86_400_000);
}

/**
 * Formatea una fecha ISO/BD como dd/mm/yyyy sin tocar timezone.
 *
 * Esto preserva la lógica que ya estaba en Turnos.tsx: el backend castea
 * fecha como date y el string llega como YYYY-MM-DD. Reordenar el substring
 * evita el off-by-one de new Date()/toLocaleDateString cuando hay desfase UTC.
 */
export function fechaLegible(iso: string | null | undefined): string {
  if (!iso) return '—';

  const [anio, mes, dia] = iso.substring(0, 10).split('-');
  if (!anio || !mes || !dia) return '—';

  return `${dia}/${mes}/${anio}`;
}

/**
 * Valida formato YYYY-MM-DD y fecha calendárica real.
 */
export function esFechaISOValida(iso: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return false;

  const [anio, mes, dia] = iso.split('-').map(Number);
  const fecha = new Date(Date.UTC(anio, mes - 1, dia));

  return (
    fecha.getUTCFullYear() === anio &&
    fecha.getUTCMonth() === mes - 1 &&
    fecha.getUTCDate() === dia
  );
}

export function fechaRelativa(iso: string | null | undefined): string {
  if (!iso) return '—';

  const fecha = new Date(iso);
  if (Number.isNaN(fecha.getTime())) return '—';

  const diff = Date.now() - fecha.getTime();

  // Si es futura, no inventamos relativo; mostramos fecha absoluta.
  if (diff < 0) {
    return fecha.toLocaleDateString('es-BO', { dateStyle: 'medium' });
  }

  const minutos = Math.floor(diff / 60_000);
  const horas = Math.floor(minutos / 60);
  const dias = Math.floor(horas / 24);
  const meses = Math.floor(dias / 30);

  if (minutos < 1) return 'recién';
  if (minutos < 60) return `hace ${minutos} min`;
  if (horas < 24) return `hace ${horas} h`;
  if (dias < 30) return `hace ${dias} d`;
  if (meses < 12) return `hace ${meses} mes${meses === 1 ? '' : 'es'}`;

  return fecha.toLocaleDateString('es-BO', { dateStyle: 'medium' });
}
