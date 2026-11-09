const TZ_BOLIVIA = 'America/La_Paz';

const MESES_CORTOS_ES = [
  'ene',
  'feb',
  'mar',
  'abr',
  'may',
  'jun',
  'jul',
  'ago',
  'sep',
  'oct',
  'nov',
  'dic',
];

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
 * Devuelve solo la parte YYYY-MM-DD de un ISO date/datetime.
 *
 * No reformatea a dd/mm/yyyy; es para tablas/detalles que muestran fecha corta ISO.
 */
export function fechaCortaISO(valor: string | null | undefined): string {
  if (!valor) return '—';
  return String(valor).substring(0, 10);
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

/**
 * Fecha tipo "10 de nov, 2026" para strings YYYY-MM-DD sin tocar timezone.
 *
 * Reemplaza el patrón date-fns:
 *   format(parseISO(fecha), "d 'de' MMM, yyyy", { locale: es })
 * evitando el off-by-one de date-only interpretado como UTC.
 */
export function fechaMedianaBolivia(iso: string | null | undefined): string {
  if (!iso) return '—';

  const texto = String(iso);
  const [anioStr, mesStr, diaStr] = texto.substring(0, 10).split('-');

  const anio = Number(anioStr);
  const mes = Number(mesStr);
  const dia = Number(diaStr);

  if (
    !Number.isFinite(anio) ||
    !Number.isFinite(mes) ||
    !Number.isFinite(dia) ||
    mes < 1 ||
    mes > 12 ||
    dia < 1 ||
    dia > 31
  ) {
    return texto;
  }

  return `${dia} de ${MESES_CORTOS_ES[mes - 1]}, ${anio}`;
}

/**
 * Hora HH:mm en hora oficial de Bolivia (America/La_Paz).
 *
 * - Acepta time-only ("14:30:00" / "14:30").
 * - Acepta ISO datetime y lo muestra en Bolivia, no en el timezone del browser.
 * - Date-only o valores inválidos devuelven '--:--' para no inventar hora.
 */
export function horaLegible(valor: string | null | undefined): string {
  if (!valor) return '--:--';

  const texto = String(valor);

  // Date-only no tiene hora util.
  if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) return '--:--';

  // Time-only: HH:mm o HH:mm:ss.
  if (/^\d{2}:\d{2}/.test(texto) && !texto.includes('T')) {
    return texto.slice(0, 5);
  }

  const fecha = new Date(texto);
  if (Number.isNaN(fecha.getTime())) return '--:--';

  try {
    return fecha.toLocaleTimeString('es-BO', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone: TZ_BOLIVIA,
    });
  } catch {
    return fecha.toLocaleTimeString('es-BO', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
  }
}

/**
 * Fecha + hora legible, equivalente al helper local formatoFecha de Reservas.
 */
export function fechaHoraLegible(valor: string | null | undefined): string {
  if (!valor) return '—';

  const texto = String(valor);
  const fecha = new Date(texto);

  if (Number.isNaN(fecha.getTime())) return texto;

  return fecha.toLocaleString('es-BO');
}

/**
 * Tiempo relativo desde un Date, preservando el wording de MisCampos:
 * "hace un momento", "hace X segundos", "hace X minutos", "hace X horas".
 */
export function tiempoRelativoDesdeDate(fecha: Date | null | undefined): string {
  if (!fecha) return '—';

  const diff = Math.floor((Date.now() - fecha.getTime()) / 1000);

  if (diff < 10) return 'hace un momento';
  if (diff < 60) return `hace ${diff} segundos`;
  if (diff < 3600) return `hace ${Math.floor(diff / 60)} minutos`;

  return `hace ${Math.floor(diff / 3600)} horas`;
}

/**
 * Minutos desde medianoche en America/La_Paz.
 *
 * Se usa para el pulso de "franja actual" sin depender del timezone del browser.
 */
export function minutosDesdeMedianocheBolivia(): number {
  try {
    const partes = new Intl.DateTimeFormat('en-GB', {
      timeZone: TZ_BOLIVIA,
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    }).formatToParts(new Date());

    const hora = Number(partes.find((p) => p.type === 'hour')?.value ?? '0');
    const minuto = Number(partes.find((p) => p.type === 'minute')?.value ?? '0');

    if (Number.isFinite(hora) && Number.isFinite(minuto)) {
      return hora * 60 + minuto;
    }
  } catch {
    // Fallback al timezone local del browser si Intl falla en algún entorno raro.
  }

  const ahora = new Date();
  return ahora.getHours() * 60 + ahora.getMinutes();
}
