type MontoInput = number | string | null | undefined;

function normalizarMonto(valor: MontoInput): number | null {
  if (valor === null || valor === undefined || valor === '') return null;

  if (typeof valor === 'number') {
    return Number.isFinite(valor) ? valor : null;
  }

  // Tolera strings decimales tipo "15.00", "15,00" o con espacios.
  const limpio = valor.trim().replace(/\s/g, '').replace(',', '.');
  const numero = Number(limpio);

  return Number.isFinite(numero) ? numero : null;
}

/**
 * Formato canónico de montos en Bs para el panel admin.
 *
 * Devuelve siempre un monto visible, incluso si llega null/inválido,
 * para evitar "Bs NaN" en UI.
 */
export function formatMontoBoB(valor: MontoInput): string {
  const numero = normalizarMonto(valor);
  if (numero === null) return 'Bs 0.00';
  return `Bs ${numero.toFixed(2)}`;
}

/**
 * Variante para valores opcionales, como precio_unitario de piscina.
 * Si no hay monto util, muestra un label amigable en vez de Bs 0.00.
 */
export function formatMontoOpcional(
  valor: MontoInput,
  vacioLabel = 'Sin monto',
): string {
  const numero = normalizarMonto(valor);
  if (numero === null) return vacioLabel;
  return `Bs ${numero.toFixed(2)}`;
}
