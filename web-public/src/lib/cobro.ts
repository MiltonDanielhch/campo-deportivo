// [S16/S12] Fuente única de la regla de cobro: cómo se lee el QR/código de
// ventanilla del payload (campo vs piscina) y cómo se arma el link wa.me.
// Pago.tsx y BloqueCobroQR.tsx consumen esto; no se duplica el algoritmo.
export interface CobroNormalizado {
  codigoPublico: string;
  qrString: string;
}

// Entrada laxa a propósito: el payload de campo (SolicitudEstadoResource) y el
// de piscina (payloadPiscina) traen estos campos con formas distintas; cuando
// veamos EstadoSolicitudPublico con cuerpo podemos estrechar el tipo.
export function normalizarCobro(raw: any): CobroNormalizado | null {
  if (!raw) return null;
  const c = raw.cobro ?? raw.datos_cobro_pendiente ?? raw;
  if (!c || typeof c !== 'object') return null;

  const qr: string | null = c.qrString ?? c.qr_string ?? null;
  const codigo: string | null =
    c.referenciaRecaudaciones ??
    c.referencia_recaudaciones ??
    c.codigo_publico ??
    c.codigoPublico ??
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
