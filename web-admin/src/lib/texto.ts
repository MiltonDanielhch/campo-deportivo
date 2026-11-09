/** Iniciales de un nombre completo (máx. 2 letras, mayúsculas). */
export function iniciales(nombre: string): string {
  return nombre
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}


/**
 * Normaliza nombre a title case para datos históricos que llegan en
 * mayúsculas/minúsculas.
 *
 * Los datos nuevos ya vienen normalizados del backend, pero la UI mantiene
 * esta normalización defensiva para listados y detalles.
 */
export function normalizarNombre(nombre?: string | null): string {
  if (!nombre) return '—';

  return nombre
    .toLowerCase()
    .split(' ')
    .map((palabra) => palabra.charAt(0).toUpperCase() + palabra.slice(1))
    .join(' ');
}
