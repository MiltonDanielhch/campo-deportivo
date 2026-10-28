// [S16/S12] Fuente única de la regla de cobro: cómo se lee el QR/código de
// ventanilla del payload (campo vs piscina) y cómo se arma el link wa.me.
// Pago.tsx y BloqueCobroQR.tsx consumen esto; no se duplica el algoritmo.
export interface CobroNormalizado {
  codigoPublico: string;
  qrString: string;
}

// Campos planos que el helper lee en cualquier rama (raíz o caja anidada).
export interface CobroBruto {
  qrString?: string | null;
  qr_string?: string | null;
  referenciaRecaudaciones?: string | null;
  referencia_recaudaciones?: string | null;
  codigo_publico?: string | null;
  codigoPublico?: string | null;
}

// Entrada del helper: la caja de cobro puede venir anidada (cobro /
// datos_cobro_pendiente) o en raíz. Extender CobroBruto hace que la unión
// CobroBruto | CobroInput sea accesible sin cast en los campos planos.
export interface CobroInput extends CobroBruto {
  cobro?: CobroBruto | null;
  datos_cobro_pendiente?: CobroBruto | null;
}

export function normalizarCobro(raw: CobroInput | null | undefined): CobroNormalizado | null {
  if (!raw) return null;
  const c: CobroBruto | null = raw.cobro ?? raw.datos_cobro_pendiente ?? null;
  const fuente: CobroBruto = c ?? raw;
  if (!fuente || typeof fuente !== 'object') return null;

  const qr: string | null = fuente.qrString ?? fuente.qr_string ?? null;
  const codigo: string | null =
    fuente.referenciaRecaudaciones ??
    fuente.referencia_recaudaciones ??
    fuente.codigo_publico ??
    fuente.codigoPublico ??
    raw.referencia_recaudaciones ??
    raw.codigo_publico ??
    null;

  const codigoFinal =
    codigo ?? (qr && qr.startsWith('SIREB:') ? qr.slice('SIREB:'.length) : null);
  const qrFinal = qr ?? (codigoFinal ? `SIREB:${codigoFinal}` : null);

  if (!codigoFinal || !qrFinal) return null;
  return { codigoPublico: codigoFinal, qrString: qrFinal };
}

// [wa.me] Plantilla única del link de WhatsApp. La doc de wa.me confirma que
// sin app instalada cae a "use WhatsApp Web" -> robusto desktop + movil.
export function construirWhatsappUrl(
  codigoSeguimiento: string,
  montoTotal: number,
  cobro: CobroNormalizado,
): string {
  return `https://wa.me/?text=${encodeURIComponent(
    `Reserva Canchas GAD Beni\nCódigo: ${codigoSeguimiento}\nMonto: Bs ${montoTotal.toFixed(
      2,
    )}\nCódigo de pago: ${cobro.codigoPublico}\nQR: ${cobro.qrString}`,
  )}`;
}
