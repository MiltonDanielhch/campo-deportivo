import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { useReactToPrint } from 'react-to-print';
import { Home, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import type { EstadoSolicitudPublico } from '@/types/reserva';
import ComprobantePDF from '@/components/comprobante/ComprobantePDF';
import EstadoReserva from '@/components/comprobante/EstadoReserva';

export default function Comprobante() {
  const { codigo } = useParams<{ codigo: string }>();
  const [datos, setDatos] = useState<EstadoSolicitudPublico | null>(null);
  const [vista, setVista] = useState<'cargando' | 'ok' | 'no_encontrada'>('cargando');
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

      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8 max-w-2xl">
          {vista === 'cargando' && (
            <Card>
              <CardContent className="py-12 text-center text-gray-600">
                Cargando comprobante...
              </CardContent>
            </Card>
          )}

          {vista === 'no_encontrada' && (
            <Card>
              <CardContent className="py-12 text-center">
                <h1 className="text-2xl font-bold mb-2">Reserva no encontrada</h1>
                <p className="text-gray-600 mb-6">
                  No se encontró ninguna reserva con ese código.
                </p>
                <Button asChild>
                  <Link to="/estado">Consultar otro código</Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {vista === 'ok' && datos && (
            <>
              {datos.estado === 'confirmada' ? (
                <>
                  <div ref={refImpresion}>
                    <ComprobantePDF datos={datos} />
                  </div>
                  <div className="flex gap-3 mt-6 print:hidden">
                    <Button onClick={handlePrint} className="flex-1">
                      <Printer className="w-4 h-4 mr-2" />
                      Imprimir / Guardar como PDF
                    </Button>
                    <Button variant="outline" asChild className="flex-1">
                      <Link to="/">
                        <Home className="w-4 h-4 mr-2" />
                        Volver al inicio
                      </Link>
                    </Button>
                  </div>
                </>
              ) : (
                <>
                  <EstadoReserva
                    estado={datos.estado}
                    montoTotal={datos.monto_total}
                    expiraEn={datos.expira_en}
                  />
                  <div className="flex gap-3 mt-6">
                    {datos.estado === 'pendiente' && (
                      <Button asChild className="flex-1">
                        <Link to={`/pago/${datos.codigo_seguimiento}`}>Ir a pagar</Link>
                      </Button>
                    )}
                    <Button variant="outline" asChild className="flex-1">
                      <Link to="/">Volver al inicio</Link>
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
