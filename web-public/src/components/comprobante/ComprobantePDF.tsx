import type { EstadoSolicitudPublico } from '@/types/reserva';

interface ComprobantePDFProps {
  datos: EstadoSolicitudPublico;
}

/**
 * Layout imprimible del comprobante (HU-D6, versión web).
 * Se renderiza igual en pantalla y dentro del diálogo de impresión.
 * Nota de alcance: react-to-print abre el diálogo del navegador
 * ("Guardar como PDF"), no genera un archivo en servidor.
 */
export default function ComprobantePDF({ datos }: ComprobantePDFProps) {
  const reservas = datos.reservas ?? [];
  const confirmadoEn = reservas[0]?.confirmado_en ?? null;

  return (
    <div className="bg-white border rounded-lg p-8 print:border-0 print:p-0">
      <div className="text-center border-b-2 border-teal-600 pb-4 mb-6">
        <p className="text-xs uppercase tracking-widest text-gray-500">
          Gobierno Autónomo Departamental del Beni
        </p>
        <h1 className="text-2xl font-bold text-teal-700 mt-1">
          Comprobante de Reserva Deportiva
        </h1>
        <p className="text-xs text-gray-500 mt-1">
          Sistema de Administración de Campos Deportivos
        </p>
      </div>

      <div className="grid grid-cols-2 gap-4 mb-6 text-sm">
        <div>
          <p className="text-gray-500">Código de seguimiento</p>
          <p className="font-mono font-bold">{datos.codigo_seguimiento}</p>
        </div>
        <div>
          <p className="text-gray-500">Monto pagado</p>
          <p className="font-bold text-green-700">Bs {datos.monto_total.toFixed(2)}</p>
        </div>
        {confirmadoEn && (
          <div>
            <p className="text-gray-500">Fecha de confirmación</p>
            <p className="font-semibold">
              {new Date(confirmadoEn).toLocaleString('es-BO')}
            </p>
          </div>
        )}
      </div>

      <h2 className="font-bold mb-2">Reservas confirmadas</h2>
      <table className="w-full text-sm border-collapse">
        <thead>
          <tr className="bg-teal-50 text-left">
            <th className="border p-2">Código</th>
            <th className="border p-2">Campo</th>
            <th className="border p-2">Fecha</th>
            <th className="border p-2">Horario</th>
          </tr>
        </thead>
        <tbody>
          {reservas.map((r) => (
            <tr key={r.codigo_reserva}>
              <td className="border p-2 font-mono text-xs">{r.codigo_reserva}</td>
              <td className="border p-2">{r.campo_nombre}</td>
              <td className="border p-2">{r.fecha}</td>
              <td className="border p-2">
                {r.hora_inicio} - {r.hora_fin}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <p className="text-xs text-gray-500 mt-6 text-center">
        Presentá estos códigos al llegar al campo deportivo. Este comprobante
        es válido sin firma ni sello.
      </p>
    </div>
  );
}
