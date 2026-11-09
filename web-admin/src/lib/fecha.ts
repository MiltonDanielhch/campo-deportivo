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
