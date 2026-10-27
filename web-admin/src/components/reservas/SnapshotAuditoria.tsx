import { Fragment } from 'react';

/**
 * [S46] Render humano de los snapshots (antes/después) de auditoría.
 * Genérico: no conoce dominio (cancha/piscina). Lo consumen ambos detalles.
 * El funcionario no lee JSON → todo cae a tabla clave→valor etiquetada.
 */
const ETIQUETAS_CAMPO: Record<string, string> = {
  estado: 'Estado',
  monto: 'Monto',
  monto_total: 'Monto total',
  monto_confirmado: 'Monto confirmado',
  subtotal: 'Subtotal',
  precio_unitario: 'Precio unitario',
  tarifa_aplicada: 'Tarifa aplicada',
  codigo_publico: 'Código público (ventanilla)',
  codigo_seguimiento: 'Código de seguimiento',
  codigo_reserva: 'Código de reserva',
  liquidacion_id: 'ID de liquidación SIREB',
  referencia_sireb: 'Referencia SIREB',
  canal: 'Canal',
  motivo: 'Motivo',
  motivo_rechazo: 'Motivo de rechazo',
  error: 'Error',
  cantidad: 'Cantidad',
  perfil: 'Perfil',
  etiqueta_sireb: 'Etiqueta SIREB',
  fecha: 'Fecha',
  hora_inicio: 'Hora de inicio',
  hora_fin: 'Hora de fin',
  items: 'Personas / perfiles',
  turno: 'Turno',
  turno_id: 'Turno',
  reserva_id: 'Reserva',
  solicitud_reserva_id: 'Solicitud',
  campo_id: 'Campo',
  variante_id: 'Variante',
  servicio_sireb_id: 'Servicio SIREB',
  tarifa_sireb_id: 'Tarifa SIREB',
  asistencia_marcada_en: 'Asistencia marcada',
  asistencia_marcada_por: 'Marcada por',
};

const CAMPOS_MONETARIOS = new Set([
  'monto', 'monto_total', 'monto_confirmado', 'subtotal', 'precio_unitario', 'tarifa_aplicada',
]);

function valorPrimitivo(clave: string, valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') return '—';
  if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
  if (CAMPOS_MONETARIOS.has(clave)) {
    const n = Number(valor);
    return Number.isNaN(n) ? String(valor) : `Bs ${n.toFixed(2)}`;
  }
  return String(valor);
}

function Etiquetas({ datos }: { datos: Record<string, unknown> }) {
  const entradas = Object.entries(datos);
  if (entradas.length === 0) return null;

  return (
    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
      {entradas.map(([clave, valor]) => (
        <Fragment key={clave}>
          <dt className="text-muted-foreground">
            {ETIQUETAS_CAMPO[clave] ?? clave.replace(/_/g, ' ')}
          </dt>
          <dd className="min-w-0 break-words font-mono text-foreground">
            <RenderizarValor clave={clave} valor={valor} />
          </dd>
        </Fragment>
      ))}
    </dl>
  );
}

function RenderizarValor({ clave, valor }: { clave: string; valor: unknown }) {
  if (Array.isArray(valor)) {
    if (valor.length === 0) return <span>—</span>;
    return (
      <div className="space-y-1">
        {valor.map((item, i) => (
          <div
            key={i}
            className={
              valor.length > 1
                ? 'rounded border border-slate-200 bg-white/60 p-1.5 dark:border-slate-700 dark:bg-slate-800/40'
                : ''
            }
          >
            {item && typeof item === 'object'
              ? <Etiquetas datos={item as Record<string, unknown>} />
              : <span>{valorPrimitivo(clave, item)}</span>}
          </div>
        ))}
      </div>
    );
  }

  if (valor && typeof valor === 'object') {
    return <Etiquetas datos={valor as Record<string, unknown>} />;
  }

  return <span>{valorPrimitivo(clave, valor)}</span>;
}

export function SnapshotEtiquetado({ titulo, datos }: { titulo: string; datos: unknown }) {
  if (datos === null || datos === undefined) return null;

  return (
    <div className="mt-1 first:mt-0">
      <p className="mb-1 font-medium text-muted-foreground">{titulo}</p>
      <RenderizarValor clave="" valor={datos} />
    </div>
  );
}
