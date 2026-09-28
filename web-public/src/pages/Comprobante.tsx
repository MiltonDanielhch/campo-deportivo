import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useReactToPrint } from 'react-to-print';
import {
  Home,
  Printer,
  Loader2,
  XCircle,
  ArrowLeft,
  ArrowRight,
  FileDown,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import type { EstadoSolicitudPublico } from '@/types/reserva';
import ComprobantePDF from '@/components/comprobante/ComprobantePDF';
import EstadoReserva from '@/components/comprobante/EstadoReserva';

export default function Comprobante() {
  const { codigo } = useParams<{ codigo: string }>();
  const [datos, setDatos] = useState<EstadoSolicitudPublico | null>(null);
  const [vista, setVista] = useState<'cargando' | 'ok' | 'no_encontrada'>(
    'cargando',
  );
  const refImpresion = useRef<HTMLDivElement>(null);

  const handlePrint = useReactToPrint({ contentRef: refImpresion });

  useEffect(() => {
    if (!codigo) return;
    api
      .get(`/public/solicitudes-reserva/${codigo}/estado`)
      .then((res) => {
        setDatos(res.data.data);
        setVista('ok');
      })
      .catch((err) => {
        if (err.response?.status === 404) setVista('no_encontrada');
      });
  }, [codigo]);

  return (
    <>
      <Helmet>
        <title>Comprobante de reserva - Canchas GAD Beni</title>
        <meta name="robots" content="noindex" />
      </Helmet>

      <div className="min-h-screen bg-slate-50 pb-16">
        <div className="container mx-auto px-4 py-6 md:py-10 max-w-3xl">
          {/* ─── Header con acciones (oculto al imprimir) ─── */}
          <div className="flex items-center justify-between gap-3 mb-6 print:hidden">
            <Button variant="ghost" asChild className="-ml-2">
              <Link to="/" className="flex items-center gap-1.5">
                <ArrowLeft className="w-4 h-4" />
                Inicio
              </Link>
            </Button>

            {vista === 'ok' && datos?.estado === 'confirmada' && (
              <Button
                onClick={handlePrint}
                className="rounded-full shadow-md shadow-teal-500/20"
              >
                <Printer className="w-4 h-4 mr-2" />
                Imprimir / PDF
              </Button>
            )}
          </div>

          {/* ─── Cargando ─── */}
          {vista === 'cargando' && (
            <Card className="border-slate-200">
              <CardContent className="py-16 text-center">
                <Loader2 className="w-10 h-10 animate-spin text-teal-600 mx-auto mb-4" />
                <p className="text-slate-600">Cargando comprobante…</p>
              </CardContent>
            </Card>
          )}

          {/* ─── No encontrada ─── */}
          {vista === 'no_encontrada' && (
            <Card className="border-slate-200">
              <CardContent className="py-16 text-center">
                <div className="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-5">
                  <XCircle className="w-10 h-10 text-slate-400" />
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  Reserva no encontrada
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  No pudimos encontrar una reserva con ese código. Verificá que
                  esté bien escrito.
                </p>
                <div className="flex flex-col sm:flex-row gap-2 justify-center">
                  <Button asChild className="rounded-full">
                    <Link to="/estado" className="flex items-center gap-2">
                      Consultar otro código
                      <ArrowRight className="w-4 h-4" />
                    </Link>
                  </Button>
                  <Button asChild variant="outline" className="rounded-full">
                    <Link to="/" className="flex items-center gap-2">
                      <Home className="w-4 h-4" />
                      Ir al inicio
                    </Link>
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}

          {/* ─── OK ─── */}
          {vista === 'ok' && datos && (
            <>
              {datos.estado === 'confirmada' ? (
                <>
                  {/* Eyebrow + título (no imprimible) */}
                  <div className="mb-6 print:hidden">
                    <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-2">
                      Reserva confirmada
                    </p>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight mb-1">
                      Tu comprobante está listo
                    </h1>
                    <p className="text-slate-600">
                      Podés imprimirlo, guardarlo como PDF o mostrarlo desde tu
                      celular al llegar a la cancha.
                    </p>
                  </div>

                  {/* El ticket imprimible */}
                  <div ref={refImpresion} className="print:block">
                    <ComprobantePDF datos={datos} />
                  </div>

                  {/* Acciones post-comprobante (no imprimibles) */}
                  <div className="flex flex-col sm:flex-row gap-2 mt-6 print:hidden">
                    <Button
                      onClick={handlePrint}
                      className="flex-1 rounded-full h-12"
                    >
                      <Printer className="w-4 h-4 mr-2" />
                      Imprimir / Guardar como PDF
                    </Button>
                    <Button
                      asChild
                      variant="outline"
                      className="flex-1 rounded-full h-12"
                    >
                      <Link to="/" className="flex items-center justify-center gap-2">
                        <Home className="w-4 h-4" />
                        Volver al inicio
                      </Link>
                    </Button>
                  </div>

                  {/* Tip final */}
                  <div className="mt-6 p-4 bg-teal-50/50 border-l-4 border-teal-500 rounded-r-lg print:hidden">
                    <p className="text-sm text-slate-700 leading-relaxed">
                      <span className="font-semibold">💡 Tip:</span> guardá este
                      comprobante en tu celular. Al llegar al campo deportivo,
                      mostrá el QR o el código al personal de atención.
                    </p>
                  </div>
                </>
              ) : (
                <>
                  {/* Estados no confirmados */}
                  <div className="mb-6">
                    <p className="text-slate-500 font-semibold uppercase tracking-widest text-xs mb-2">
                      Código: {datos.codigo_seguimiento}
                    </p>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight">
                      Estado de tu reserva
                    </h1>
                  </div>

                  <EstadoReserva
                    estado={datos.estado}
                    montoTotal={datos.monto_total}
                    expiraEn={datos.expira_en}
                    codigoSeguimiento={datos.codigo_seguimiento}
                    mostrarAcciones
                  />

                  <div className="mt-6 flex flex-col sm:flex-row gap-2 print:hidden">
                    {datos.estado === 'pendiente' && (
                      <Button
                        asChild
                        className="flex-1 rounded-full h-12"
                      >
                        <Link
                          to={`/pago/${datos.codigo_seguimiento}`}
                          className="flex items-center justify-center gap-2"
                        >
                          <FileDown className="w-4 h-4" />
                          Ir a pagar ahora
                          <ArrowRight className="w-4 h-4" />
                        </Link>
                      </Button>
                    )}
                    <Button
                      asChild
                      variant="outline"
                      className="flex-1 rounded-full h-12"
                    >
                      <Link to="/" className="flex items-center justify-center gap-2">
                        <Home className="w-4 h-4" />
                        Volver al inicio
                      </Link>
                    </Button>
                  </div>
                </>
              )}
            </>
          )}
        </div>
      </div>
    </>
  );
}
