/** Iniciales de un nombre completo (máx. 2 letras, mayúsculas). */
export function iniciales(nombre: string): string {
  return nombre
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}
