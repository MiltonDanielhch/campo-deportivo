import { useCallback, useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { TimerOff, AlertTriangle, CheckCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import CuentaRegresiva from '@/components/pago/CuentaRegresiva';
import MedioDePago from '@/components/pago/MedioDePago';

interface EstadoPago {
  codigo_seguimiento: string;
  estado: string;
  monto_total: number;
  expira_en: string | null;
  datos_cobro_pendiente: {
    qr_string?: string | null;
    qr_image_base64?: string | null;
    checkout_url?: string | null;
  } | null;
}

type Vista = 'cargando' | 'pendiente' | 'expirada' | 'rechazada' | 'no_encontrada';

export default function Pago() {
  const { codigo } = useParams<{ codigo: string }>();
  const navigate = useNavigate();
  const [vista, setVista] = useState<Vista>('cargando');
  const [estado, setEstado] = useState<EstadoPago | null>(null);

  const consultarEstado = useCallback(async () => {
    if (!codigo) return;
    try {
      const res = await api.get(`/public/solicitudes-reserva/${codigo}/estado`);
      const data: EstadoPago = res.data.data;
      setEstado(data);

      if (data.estado === 'confirmada') {
        navigate(`/comprobante/${codigo}`, { replace: true });
        return;
      }
      if (data.estado === 'expirada') setVista('expirada');
      else if (data.estado === 'rechazada') setVista('rechazada');
      else setVista('pendiente');
    } catch (err: any) {
      if (err.response?.status === 404) {
        setVista('no_encontrada');
      }
      // Otros errores: se reintenta en el siguiente tick del polling
    }
  }, [codigo, navigate]);

  // GET inicial al montar: la página se autoabastece desde la URL
  useEffect(() => {
    consultarEstado();
  }, [consultarEstado]);

  // Polling cada 5 segundos
  useEffect(() => {
    if (vista !== 'pendiente') return;
    const interval = setInterval(consultarEstado, 5000);
    return () => clearInterval(interval);
  }, [vista, consultarEstado]);

  const handleExpirarLocal = useCallback(() => {
    setVista('expirada');
  }, []);

  return (
    <>
      <Helmet>
        <title>Pago de reserva - Canchas GAD Beni</title>
        <meta name="robots" content="noindex" />
      </Helmet>

      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8 max-w-xl">
          {vista === 'cargando' && (
            <Card>
              <CardContent className="py-12 text-center text-gray-600">
                Cargando tu reserva...
              </CardContent>
            </Card>
          )}

          {vista === 'pendiente' && estado && (
            <Card>
              <CardContent className="py-8">
                <CuentaRegresiva
                  expiraEn={estado.expira_en!}
                  onExpirar={handleExpirarLocal}
                />
                <div className="text-center mb-6">
                  <p className="text-3xl font-bold text-green-700">
                    Bs {estado.monto_total.toFixed(2)}
                  </p>
                  <p className="text-sm text-gray-600 mt-2 font-mono">
                    Código: {estado.codigo_seguimiento}
                  </p>
                  <p className="text-xs text-gray-500">
                    Guardalo por si necesitás soporte
                  </p>
                </div>
                <MedioDePago datos={estado.datos_cobro_pendiente} />
                <p className="text-xs text-gray-500 text-center mt-6">
                  Esta página detecta automáticamente cuando el Core confirma
                  tu pago y te muestra el comprobante.
                </p>
              </CardContent>
            </Card>
          )}

          {vista === 'expirada' && (
            <Card>
              <CardContent className="py-12 text-center">
                <TimerOff className="w-16 h-16 text-red-500 mx-auto mb-4" />
                <h1 className="text-2xl font-bold mb-2">
                  El tiempo para pagar expiró
                </h1>
                <p className="text-gray-600 mb-6">
                  Tu solicitud quedó sin pago. Podés volver a elegir tus franjas.
                </p>
                <Button asChild>
                  <Link to="/campos">Volver a elegir franjas</Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {vista === 'rechazada' && (
            <Card>
              <CardContent className="py-12 text-center">
                <AlertTriangle className="w-16 h-16 text-orange-500 mx-auto mb-4" />
                <h1 className="text-2xl font-bold mb-2">
                  Tu pago no pudo procesarse
                </h1>
                <p className="text-gray-600 mb-6">
                  El sistema de recaudaciones rechazó el intento de cobro.
                  Intentá nuevamente con otro medio de pago.
                </p>
                <Button asChild>
                  <Link to="/campos">Volver a elegir franjas</Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {vista === 'no_encontrada' && (
            <Card>
              <CardContent className="py-12 text-center">
                <CheckCircle className="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <h1 className="text-2xl font-bold mb-2">Reserva no encontrada</h1>
                <p className="text-gray-600 mb-6">
                  El código de seguimiento no existe o ya fue procesado.
                </p>
                <Button asChild>
                  <Link to="/campos">Ir al inicio</Link>
                </Button>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </>
  );
}