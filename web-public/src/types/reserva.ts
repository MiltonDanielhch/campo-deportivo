export interface ReservaConfirmada {
  codigo_reserva: string;
  campo_nombre: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  confirmado_en?: string | null;
  /** Solo piscina: perfil y cantidad de personas de cada detalle/turno. */
  perfil?: 'mayor_12' | 'menor_12' | 'institucion' | string;
  cantidad?: number;
}

export interface DatosCobroPendiente {
  qr_string: string;
  codigo_publico: string;
  checkout_url: string | null;
}

export interface EstadoSolicitudPublico {
  /** 'campo' (canchas) o 'piscina'. La consulta unificada lo agrega. */
  tipo?: 'campo' | 'piscina';
  codigo_seguimiento: string;
  estado: string;
  monto_total: number;
  // [3b] lo que SIREB reportó al confirmar (autoritativo post-pago). Viaja solo en
  // 'confirmada' (resource cancha + payloadPiscina). Ausente/null en pendiente y en
  // confirmadas viejas -> ComprobantePDF cae a monto_total con label neutro.
  monto_confirmado?: number | null;
  expira_en: string | null;
  // Viaja solo en estado 'pendiente' (reabrir link de pago / ver mismo QR).
  datos_cobro_pendiente?: DatosCobroPendiente | null;
  // Viaja solo en estado 'confirmada'.
  reservas?: ReservaConfirmada[];
  // TODO backend: exponer también en 'confirmada' para mostrar el nº de recibo
  // SIREB en el comprobante (conciliación fiscal, deuda D-06). Hoy el resource
  // solo lo manda dentro de datos_cobro_pendiente cuando está pendiente.
  referencia_recaudaciones?: string | null;
}
