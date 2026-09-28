import { QRCodeSVG } from 'qrcode.react';
import {
  Calendar,
  Clock,
  MapPin,
  Hash,
  CheckCircle2,
} from 'lucide-react';
import type { EstadoSolicitudPublico } from '@/types/reserva';

interface ComprobantePDFProps {
  datos: EstadoSolicitudPublico;
}

const DIAS_SEMANA = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const MESES = [
  'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
  'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic',
];

function formatearFecha(fechaISO: string): string {
  const [y, m, d] = fechaISO.split('-').map(Number);
  const fecha = new Date(y, m - 1, d);
  const dia = DIAS_SEMANA[fecha.getDay()];
  return `${dia} ${d} de ${MESES[m - 1]} ${y}`;
}

export default function ComprobantePDF({ datos }: ComprobantePDFProps) {
  const reservas = datos.reservas ?? [];
  const confirmadoEn = reservas[0]?.confirmado_en ?? null;
  const esConfirmada = datos.estado === 'confirmada';

  return (
    <div className="relative bg-white rounded-2xl border-2 border-slate-200 shadow-lg print:border-0 print:shadow-none print:rounded-none overflow-hidden">
      {/* ─── Marca de agua "CONFIRMADO" (solo al imprimir) ─── */}
      {esConfirmada && (
        <div
          className="hidden print:block absolute inset-0 pointer-events-none z-0"
          style={{
            display: 'none',
          }}
        />
      )}
      <style>{`
        @media print {
          .marca-agua-confirmado {
            display: flex !important;
            position: absolute;
            inset: 0;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
          }
          .marca-agua-confirmado span {
            font-size: 120px;
            font-weight: 900;
            color: rgba(16, 185, 129, 0.08);
            transform: rotate(-30deg);
            letter-spacing: 20px;
            user-select: none;
          }
        }
      `}</style>
      {esConfirmada && (
        <div className="marca-agua-confirmado" style={{ display: 'none' }}>
          <span>CONFIRMADO</span>
        </div>
      )}

      <div className="relative z-10 p-6 md:p-10">
        {/* ─── Header institucional ─── */}
        <div className="flex items-start justify-between gap-4 pb-6 mb-6 border-b-2 border-teal-600">
          <div>
            <p className="text-[10px] font-semibold uppercase tracking-widest text-slate-500">
              Gobierno Autónomo Departamental del Beni
            </p>
            <h1 className="text-xl md:text-2xl font-bold text-teal-700 mt-1 tracking-tight">
              Comprobante de Reserva Deportiva
            </h1>
            <p className="text-xs text-slate-500 mt-1">
              Sistema de Administración de Campos Deportivos
            </p>
          </div>

          {/* Badge de estado (solo print/pantalla) */}
          {esConfirmada && (
            <div className="flex-shrink-0 bg-emerald-100 text-emerald-700 rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5 print:bg-emerald-50 print:border print:border-emerald-300">
              <CheckCircle2 className="w-3.5 h-3.5" />
              Confirmado
            </div>
          )}
        </div>

        {/* ─── Datos principales ─── */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div className="bg-slate-50 rounded-xl p-4 print:bg-transparent print:border print:border-slate-200">
            <div className="flex items-center gap-2 mb-1">
              <Hash className="w-3.5 h-3.5 text-slate-400" />
              <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Código de seguimiento
              </p>
            </div>
            <p className="font-mono font-bold text-sm md:text-base text-slate-900 break-all">
              {datos.codigo_seguimiento}
            </p>
          </div>

          <div className="bg-slate-50 rounded-xl p-4 print:bg-transparent print:border print:border-slate-200">
            <div className="flex items-center gap-2 mb-1">
              <Clock className="w-3.5 h-3.5 text-slate-400" />
              <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                Confirmado el
              </p>
            </div>
            <p className="font-semibold text-sm text-slate-900">
              {confirmadoEn
                ? new Date(confirmadoEn).toLocaleString('es-BO', {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  })
                : '—'}
            </p>
          </div>

          <div className="bg-emerald-50 rounded-xl p-4 print:bg-transparent print:border-2 print:border-emerald-300">
            <p className="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 mb-1">
              Monto total pagado
            </p>
            <p className="text-2xl md:text-3xl font-bold text-emerald-700 tabular-nums">
              Bs {datos.monto_total.toFixed(2)}
            </p>
          </div>
        </div>

        {/* ─── Tabla de franjas ─── */}
        <div className="mb-8">
          <h2 className="text-sm font-bold uppercase tracking-wider text-slate-700 mb-3">
            Franjas confirmadas ({reservas.length})
          </h2>

          <div className="space-y-2">
            {reservas.map((r, i) => (
              <div
                key={r.codigo_reserva}
                className="flex items-start gap-3 bg-slate-50 rounded-xl p-4 print:bg-transparent print:border print:border-slate-200"
              >
                {/* Número de orden */}
                <div className="w-8 h-8 rounded-lg bg-teal-600 text-white flex items-center justify-center flex-shrink-0 font-bold text-sm print:bg-teal-100 print:text-teal-700">
                  {i + 1}
                </div>

                <div className="flex-1 min-w-0">
                  <p className="font-bold text-slate-900 mb-1 flex items-center gap-1.5">
                    <MapPin className="w-3.5 h-3.5 text-teal-600 flex-shrink-0" />
                    <span className="truncate">{r.campo_nombre}</span>
                  </p>

                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                    <span className="flex items-center gap-1">
                      <Calendar className="w-3.5 h-3.5 text-slate-400" />
                      {formatearFecha(r.fecha)}
                    </span>
                    <span className="flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5 text-slate-400" />
                      {r.hora_inicio.slice(0, 5)}–{r.hora_fin.slice(0, 5)}
                    </span>
                  </div>

                  <p className="text-[10px] font-mono text-slate-400 mt-1.5">
                    Código: {r.codigo_reserva}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* ─── Pie: QR + instrucciones ─── */}
        <div className="flex flex-col md:flex-row items-center gap-6 pt-6 border-t-2 border-dashed border-slate-200">
          {/* QR grande */}
          <div className="flex-shrink-0">
            <div className="relative p-4 bg-white rounded-xl border-2 border-slate-200 print:border-slate-400">
              {/* Esquinas decorativas */}
              <div className="absolute top-0 left-0 w-4 h-4 border-t-2 border-l-2 border-teal-600 rounded-tl-lg" />
              <div className="absolute top-0 right-0 w-4 h-4 border-t-2 border-r-2 border-teal-600 rounded-tr-lg" />
              <div className="absolute bottom-0 left-0 w-4 h-4 border-b-2 border-l-2 border-teal-600 rounded-bl-lg" />
              <div className="absolute bottom-0 right-0 w-4 h-4 border-b-2 border-r-2 border-teal-600 rounded-br-lg" />
              <QRCodeSVG
                value={datos.codigo_seguimiento}
                size={140}
                level="M"
              />
            </div>
          </div>

          {/* Instrucciones */}
          <div className="flex-1 text-center md:text-left">
            <p className="text-sm font-bold text-slate-900 mb-1">
              Presentá este comprobante al llegar
            </p>
            <p className="text-xs text-slate-600 leading-relaxed mb-3">
              Mostrá el código QR o el código de seguimiento al personal del
              campo deportivo. No hace falta imprimirlo: podés mostrarlo desde
              tu celular.
            </p>
            <p className="text-[10px] text-slate-500 italic">
              Este comprobante es válido sin firma ni sello · Gobierno Autónomo
              Departamental del Beni
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
