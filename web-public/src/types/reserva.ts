export interface ReservaConfirmada {
  codigo_reserva: string;
  campo_nombre: string;
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  confirmado_en?: string | null;
}

export interface DatosCobroPendiente {
  qr_string: string;
  codigo_publico: string;
  checkout_url: string | null;
}

export interface EstadoSolicitudPublico {
  codigo_seguimiento: string;
  estado: string;
  monto_total: number;
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
