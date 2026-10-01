import { useCallback, useEffect, useMemo, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import {
  TimerOff,
  AlertTriangle,
  CheckCircle2,
  Loader2,
  Wifi,
  ArrowRight,
  Copy,
  Check,
  ExternalLink,
  ServerCrash,
  CreditCard,
  Ban,
  Clock,
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { api } from '@/lib/api';
import CuentaRegresiva from '@/components/pago/CuentaRegresiva';
import StepsReserva from '@/components/layout/StepsReserva';

interface EstadoPago {
  codigo_seguimiento: string;
  estado: string;
  monto_total: number;
  expira_en: string | null;
  motivo_rechazo?: string | null;
  cobro?: any;
  datos_cobro_pendiente?: any;
  referencia_recaudaciones?: string | null;
  codigo_publico?: string | null;
}

interface CobroNormalizado {
  codigoPublico: string;
  qrString: string;
}

function normalizarCobro(raw: EstadoPago | null): CobroNormalizado | null {
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

// ─── NUEVO: Mapeo de motivos de rechazo a mensajes específicos ───
interface MensajeRechazo {
  titulo: string;
  descripcion: string;
  icono: any;
  color: string;
}

function obtenerMensajeRechazo(motivo: string | null | undefined): MensajeRechazo {
  switch (motivo) {
    case 'error_cobro_inicial':
      return {
        titulo: 'No pudimos conectar con el sistema de pagos',
        descripcion:
          'El sistema de recaudaciones no está disponible en este momento. Intentá nuevamente en unos minutos o contactá a soporte si el problema persiste.',
        icono: ServerCrash,
        color: 'orange',
      };
    case 'pago_rechazado_core':
      return {
        titulo: 'Tu pago no pudo procesarse',
        descripcion:
          'El banco rechazó el intento de pago. Verificá con tu entidad bancaria e intentá nuevamente con otro medio de pago.',
        icono: CreditCard,
        color: 'orange',
      };
    case 'liquidacion_anulada_core':
      return {
        titulo: 'La liquidación fue anulada',
        descripcion:
          'El sistema de recaudaciones anuló la orden de cobro. Por favor generá una nueva reserva desde el catálogo de canchas.',
        icono: Ban,
        color: 'red',
      };
    case 'expirada_sin_pago':
      return {
        titulo: 'El tiempo para pagar expiró',
        descripcion:
          'Tu solicitud quedó sin pago. Las franjas que habías reservado vuelven a estar disponibles. Generá una nueva reserva.',
        icono: Clock,
        color: 'red',
      };
    case 'datos_pagador_incompletos':
      return {
        titulo: 'Datos del solicitante incompletos',
        descripcion:
          'Faltan datos necesarios para emitir la orden de cobro (CI/NIT o nombre). Por favor generá una nueva reserva completando todos los campos.',
        icono: AlertTriangle,
        color: 'orange',
      };
    default:
      // Mensaje genérico para motivos desconocidos o null
      return {
        titulo: 'Tu solicitud no pudo completarse',
        descripcion:
          'El sistema de recaudaciones rechazó el intento de cobro. Intentá nuevamente generando una nueva reserva.',
        icono: AlertTriangle,
        color: 'orange',
      };
  }
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
  const [copiado, setCopiado] = useState(false);

  const cobro = useMemo(() => normalizarCobro(estado), [estado]);

  // ─── NUEVO: Obtener mensaje específico según motivo_rechazo ───
  const mensajeRechazo = useMemo(
    () => obtenerMensajeRechazo(estado?.motivo_rechazo),
    [estado?.motivo_rechazo]
  );

  const SIREB_PANEL_URL =
    import.meta.env.VITE_SIREB_PANEL_URL ||
    'https://test.sireb.beni.gob.bo/panel/liquidaciones';

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

  const handleCopiarCodigo = () => {
    if (cobro?.codigoPublico) {
      navigator.clipboard.writeText(cobro.codigoPublico);
      setCopiado(true);
      setTimeout(() => setCopiado(false), 2000);
    }
  };

  // ─── Helper para clases de color ───
  const getColorClasses = (color: string) => {
    if (color === 'red') {
      return {
        bg: 'bg-red-50',
        iconBg: 'bg-red-100',
        icon: 'text-red-500',
        border: 'border-red-200',
      };
    }
    return {
      bg: 'bg-orange-50',
      iconBg: 'bg-orange-100',
      icon: 'text-orange-500',
      border: 'border-orange-200',
    };
  };

  const colorClasses = getColorClasses(mensajeRechazo.color);
  const IconoRechazo = mensajeRechazo.icono;

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
                Pagá en ventanilla o escaneá el QR
              </h1>
              <p className="text-slate-600">
                Generamos tu orden de cobro. Detectamos el pago automáticamente en cuanto lo valides en el banco.
              </p>
            </div>
          )}

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
              {estado.expira_en && (
                <CuentaRegresiva
                  expiraEn={estado.expira_en}
                  onExpirar={handleExpirarLocal}
                />
              )}

              <Card className="border-slate-200 shadow-sm">
                <CardContent className="p-6">
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

                  <div className="text-center mb-6">
                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">
                      Monto a pagar
                    </p>
                    <p className="text-4xl md:text-5xl font-bold text-slate-900 tabular-nums">
                      Bs {estado.monto_total.toFixed(2)}
                    </p>
                  </div>

                  {cobro && (
                    <div className="space-y-6">
                      <div className="flex justify-center">
                        <div className="p-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                          <QRCodeSVG
                            value={cobro.qrString}
                            size={200}
                            level="H"
                          />
                        </div>
                      </div>

                      <div className="text-center space-y-3">
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                          Código de pago en ventanilla
                        </p>
                        <div className="flex items-center justify-center gap-3">
                          <p className="text-2xl md:text-3xl font-mono font-bold text-slate-900 tracking-widest">
                            {cobro.codigoPublico}
                          </p>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={handleCopiarCodigo}
                            className="h-9 w-9 text-slate-500 hover:text-teal-600"
                            title="Copiar código"
                          >
                            {copiado ? (
                              <Check className="w-4 h-4 text-green-500" />
                            ) : (
                              <Copy className="w-4 h-4" />
                            )}
                          </Button>
                        </div>
                        <p className="text-sm text-slate-600 max-w-sm mx-auto">
                          Presentá este código o escaneá el QR en ventanilla del{' '}
                          <strong>Banco Unión</strong> junto con tu CI para pagar
                          tu reserva.
                        </p>
                      </div>

                      <div className="pt-4 border-t border-slate-100 text-center">
                        <a
                          href={`${SIREB_PANEL_URL}/${cobro.codigoPublico}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-2 text-sm text-teal-600 hover:text-teal-700 font-medium"
                        >
                          Verificar estado en el panel oficial de SIREB
                          <ExternalLink className="w-3.5 h-3.5" />
                        </a>
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>

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

          {/* ─── Expirada ─── ─── NUEVO: Usa mensaje específico según motivo ─── */}
          {vista === 'expirada' && (
            <Card className={colorClasses.border}>
              <CardContent className="py-16 text-center">
                <div className={`w-20 h-20 mx-auto rounded-full ${colorClasses.iconBg} flex items-center justify-center mb-5`}>
                  {estado?.motivo_rechazo === 'expirada_sin_pago' ? (
                    <IconoRechazo className={`w-10 h-10 ${colorClasses.icon}`} />
                  ) : (
                    <TimerOff className="w-10 h-10 text-red-500" />
                  )}
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  {estado?.motivo_rechazo === 'expirada_sin_pago'
                    ? mensajeRechazo.titulo
                    : 'El tiempo para pagar expiró'}
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  {estado?.motivo_rechazo === 'expirada_sin_pago'
                    ? mensajeRechazo.descripcion
                    : 'Tu solicitud quedó sin pago. Las franjas que habías reservado vuelven a estar disponibles.'}
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

          {/* ─── Rechazada ─── ─── NUEVO: Usa mensaje específico según motivo ─── */}
          {vista === 'rechazada' && (
            <Card className={colorClasses.border}>
              <CardContent className="py-16 text-center">
                <div className={`w-20 h-20 mx-auto rounded-full ${colorClasses.iconBg} flex items-center justify-center mb-5`}>
                  <IconoRechazo className={`w-10 h-10 ${colorClasses.icon}`} />
                </div>
                <h1 className="text-2xl font-bold mb-2 tracking-tight">
                  {mensajeRechazo.titulo}
                </h1>
                <p className="text-slate-600 mb-6 max-w-md mx-auto">
                  {mensajeRechazo.descripcion}
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
