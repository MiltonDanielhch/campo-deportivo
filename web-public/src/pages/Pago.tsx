import { useCallback, useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  TimerOff,
  AlertTriangle,
  CheckCircle2,
  Loader2,
  Wifi,
  ArrowRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import CuentaRegresiva from '@/components/pago/CuentaRegresiva';
import MedioDePago from '@/components/pago/MedioDePago';
import StepsReserva from '@/components/layout/StepsReserva';

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

type Vista =
  | 'cargando'
  | 'pendiente'
  | 'expirada'
  | 'rechazada'
  | 'no_encontrada';

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
    }
  }, [codigo, navigate]);

  useEffect(() => {
    consultarEstado();
  }, [consultarEstado]);

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

      <div className="min-h-screen bg-slate-50">
        <div className="container mx-auto px-4 py-6 md:py-10 max-w-3xl">
          {/* ─── Header ─── */}
          {vista === 'pendiente' && (
            <div className="mb-6">
              <p className="text-teal-600 font-semibold uppercase tracking-widest text-xs mb-2">
                Paso 3 de 3
              </p>
              <h1 className="text-2xl md:text-4xl font-bold tracking-tight mb-2">
                Escaneá el QR para pagar
              </h1>
              <p className="text-slate-600">
                Usá la app de tu banco para completar la reserva. Detectamos el pago automáticamente.
              </p>
            </div>
          )}

          {/* ─── Stepper (solo en vista pendiente) ─── */}
          {vista === 'pendiente' && <StepsReserva pasoActual={3} />}

          {/* ─── Cargando ─── */}
          {vista === 'cargando' && (
            <Card className="border-slate-200">
              <CardContent className="py-16 text-center">
                <Loader2 className="w-10 h-10 animate-spin text-teal-600 mx-auto mb-4" />
                <p className="text-slate-600">Cargando tu reserva…</p>
              </CardContent>
            </Card>
          )}

          {/* ─── Pendiente ─── */}
          {vista === 'pendiente' && estado && (
            <div className="space-y-5">
              {/* Countdown */}
              {estado.expira_en && (
                <CuentaRegresiva
                  expiraEn={estado.expira_en}
                  onExpirar={handleExpirarLocal}
                />
              )}

              {/* Card QR */}
              <Card className="border-slate-200 shadow-sm">
                <CardContent className="p-6">
                  {/* Código de seguimiento */}
                  <div className="text-center mb-5 pb-5 border-b border-slate-100">
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                      Código de seguimiento
                    </p>
                    <p className="text-lg font-mono font-bold text-slate-900">
                      {estado.codigo_seguimiento}
                    </p>
                    <p className="text-xs text-slate-500 mt-1">
                      Guardalo por si necesitás soporte
                    </p>
                  </div>

                  {/* Monto destacado */}
                  <div className="text-center mb-6">
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                      Monto a pagar
                    </p>
                    <p className="text-4xl md:text-5xl font-bold text-slate-900 tabular-nums">
                      Bs {estado.monto_total.toFixed(2)}
                    </p>
                  </div>

                  {/* QR + instrucciones */}
                  <MedioDePago
                    datos={estado.datos_cobro_pendiente}
                    monto={estado.monto_total}
                  />
                </CardContent>
              </Card>

              {/* Pill de estado vivo (polling) */}
              <div className="flex items-center justify-center gap-2 py-3 px-4 bg-white/60 backdrop-blur rounded-full border border-slate-200 mx-auto w-fit">
                <span className="relative flex h-2.5 w-2.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75" />
                  <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-teal-500" />
                </span>
                <Wifi className="w-3.5 h-3.5 text-teal-600" />
                <span className="text-xs font-medium text-slate-700">
                  Esperando confirmación del pago…
                </span>
              </div>
            </div>
          )}

          {/* ─── Expirada ─── */}
          {vista === 'expirada' && (
            <Card className="border-red-200">
              <CardContent className="py-16 text-center">
                <div className="w-20 h-20 mx-auto rounded-full bg-red-50 flex items-center justify-center mb-5">
                  <TimerOff className="w-10 h-10 text-red-500" />
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  El tiempo para pagar expiró
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  Tu solicitud quedó sin pago. Las franjas que habías reservado
                  vuelven a estar disponibles.
                </p>
                <Button asChild className="rounded-full">
                  <Link to="/campos" className="flex items-center gap-2">
                    Volver a elegir franjas
                    <ArrowRight className="w-4 h-4" />
                  </Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {/* ─── Rechazada ─── */}
          {vista === 'rechazada' && (
            <Card className="border-orange-200">
              <CardContent className="py-16 text-center">
                <div className="w-20 h-20 mx-auto rounded-full bg-orange-50 flex items-center justify-center mb-5">
                  <AlertTriangle className="w-10 h-10 text-orange-500" />
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  Tu pago no pudo procesarse
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  El sistema de recaudaciones rechazó el intento de cobro.
                  Intentá nuevamente con otro medio de pago.
                </p>
                <Button asChild className="rounded-full">
                  <Link to="/campos" className="flex items-center gap-2">
                    Volver a elegir franjas
                    <ArrowRight className="w-4 h-4" />
                  </Link>
                </Button>
              </CardContent>
            </Card>
          )}

          {/* ─── No encontrada ─── */}
          {vista === 'no_encontrada' && (
            <Card className="border-slate-200">
              <CardContent className="py-16 text-center">
                <div className="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-5">
                  <CheckCircle2 className="w-10 h-10 text-slate-400" />
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  Reserva no encontrada
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  El código de seguimiento no existe o ya fue procesado.
                </p>
                <Button asChild variant="outline" className="rounded-full">
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
