import { useState, useEffect, useRef } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';
import {
  Search,
  Ticket,
  FileSearch,
  Loader2,
  XCircle,
  ArrowRight,
  RefreshCw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api } from '@/lib/api';
import type { EstadoSolicitudPublico } from '@/types/reserva';
import ComprobantePDF from '@/components/comprobante/ComprobantePDF';
import EstadoReserva from '@/components/comprobante/EstadoReserva';

const POLLING_INTERVAL_MS = 10000; // Verificar cada 10 segundos

export default function ConsultarEstado() {
  const [codigo, setCodigo] = useState('');
  const [datos, setDatos] = useState<EstadoSolicitudPublico | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);
  const [yaConsulto, setYaConsulto] = useState(false);
  const [pollingActivo, setPollingActivo] = useState(false);
  const pollingRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // El backend unificado marca el tipo; si no, lo inferimos por el prefijo.
  const esPiscina =
    datos?.tipo === 'piscina' ||
    (datos?.codigo_seguimiento?.startsWith('PIS-') ?? false);

  const comprobantePath = esPiscina
    ? `/comprobante-piscina/${datos?.codigo_seguimiento}`
    : `/comprobante/${datos?.codigo_seguimiento}`;

  const consultar = async () => {
    const limpio = codigo.trim().toUpperCase();
    if (!limpio) return;

    setCargando(true);
    setError(null);
    setDatos(null);
    setYaConsulto(true);

    try {
      // Endpoint unificado: acepta código de seguimiento (RES-/PIS-),
      // código de ventanilla (K699-…), CI/NIT o teléfono.
      const res = await api.get(`/public/consultas/${encodeURIComponent(limpio)}/estado`);
      setDatos(res.data.data);
    } catch (err: any) {
      if (err.response?.status === 404) {
        setError(
          'No encontramos ninguna reserva con ese código, CI/NIT o teléfono. Verificá que esté bien escrito.',
        );
      } else {
        setError('Error de conexión. Intentá nuevamente en unos segundos.');
      }
    } finally {
      setCargando(false);
    }
  };

  // Configurar polling cuando la solicitud está pendiente
  useEffect(() => {
    // Solo hacer polling si hay datos, está pendiente, y no expiró
    if (!datos || datos.estado !== 'pendiente') {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
        setPollingActivo(false);
      }
      return;
    }

    // Verificar si aún no expiró
    if (datos.expira_en && new Date(datos.expira_en) < new Date()) {
      return;
    }

    // Iniciar polling
    if (!pollingRef.current) {
      setPollingActivo(true);
      pollingRef.current = setInterval(async () => {
        try {
          const res = await api.get(
            `/public/consultas/${encodeURIComponent(codigo.trim().toUpperCase())}/estado`,
          );
          const nuevosDatos = res.data.data;

          setDatos(nuevosDatos);

          // Si cambió a confirmada, detener polling
          if (nuevosDatos.estado === 'confirmada') {
            if (pollingRef.current) {
              clearInterval(pollingRef.current);
              pollingRef.current = null;
              setPollingActivo(false);
            }
          }
        } catch (err) {
          // Silenciar errores de polling para no interrumpir al usuario
          console.error('Error en polling de estado:', err);
        }
      }, POLLING_INTERVAL_MS);
    }

    // Cleanup
    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    };
  }, [datos, codigo]);

  return (
    <>
      <Helmet>
        <title>Consultar estado de mi reserva - Canchas GAD Beni</title>
        <meta
          name="description"
          content="Consultá el estado de tu reserva de cancha con tu código de seguimiento."
        />
      </Helmet>

      <div className="min-h-screen bg-slate-50">
        <div className="container mx-auto px-4 py-10 md:py-16 max-w-3xl">
          {/* ─── Hero con buscador ─── */}
          <div className="text-center mb-8">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-50 border border-teal-100 text-xs font-semibold uppercase tracking-widest text-teal-700 mb-4">
              <Ticket className="w-3.5 h-3.5" />
              Servicio ciudadano
            </div>

            <h1 className="text-3xl md:text-5xl font-bold tracking-tight mb-3">
              Consultá tu reserva
            </h1>
            <p className="text-slate-600 text-lg max-w-xl mx-auto">
              Ingresá tu código de seguimiento, el código de pago en ventanilla,
              tu CI/NIT o teléfono para ver el estado de tu reserva, descargar el
              comprobante o volver a pagar.
            </p>
          </div>

          {/* ─── Buscador grande ─── */}
          <Card className="border-slate-200 shadow-sm mb-8">
            <CardContent className="p-5 md:p-6">
              <Label
                htmlFor="codigo"
                className="flex items-center gap-1.5 text-sm font-semibold mb-2"
              >
                <FileSearch className="w-4 h-4 text-teal-600" />
                Código, CI/NIT o teléfono
              </Label>

              <div className="flex flex-col sm:flex-row gap-2">
                <div className="relative flex-1">
                  <Input
                    id="codigo"
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value.toUpperCase())}
                    onKeyDown={(e) => e.key === 'Enter' && consultar()}
                    placeholder="RES-… / PIS-… / K699-… / CI / teléfono"
                    className="font-mono uppercase h-12 text-base pl-4 pr-4 bg-slate-50 border-slate-200 focus:bg-white"
                    disabled={cargando}
                    autoComplete="off"
                    spellCheck={false}
                  />
                </div>
                <Button
                  onClick={consultar}
                  disabled={cargando || !codigo.trim()}
                  className="h-12 px-6 rounded-xl font-semibold"
                >
                  {cargando ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Buscando…
                    </>
                  ) : (
                    <>
                      <Search className="w-4 h-4 mr-2" />
                      Consultar
                    </>
                  )}
                </Button>
              </div>

              <p className="text-xs text-slate-500 mt-3 flex items-start gap-1.5">
                <span className="text-teal-600">💡</span>
                <span>
                  Aceptamos el código de seguimiento, el código de pago en
                  ventanilla, tu CI/NIT o teléfono:{' '}
                  <code className="font-mono text-[11px] bg-slate-100 px-1.5 py-0.5 rounded">
                    RES-YYYYMMDD-XXXXXX
                  </code>{' '}
                  <code className="font-mono text-[11px] bg-slate-100 px-1.5 py-0.5 rounded">
                    PIS-YYYYMMDD-XXXXXX
                  </code>{' '}
                  <code className="font-mono text-[11px] bg-slate-100 px-1.5 py-0.5 rounded">
                    K699-88PZ-J7G
                  </code>
                </span>
              </p>
            </CardContent>
          </Card>

          {/* ─── Resultados ─── */}
          {error && (
            <Card className="border-red-200 bg-red-50/50 mb-6">
              <CardContent className="py-6 flex items-start gap-4">
                <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                  <XCircle className="w-5 h-5 text-red-600" />
                </div>
                <div className="flex-1">
                  <p className="font-semibold text-red-900 mb-1">
                    No pudimos encontrar tu reserva
                  </p>
                  <p className="text-sm text-red-700">{error}</p>
                  <div className="flex gap-3 mt-4">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setCodigo('');
                        setError(null);
                        setYaConsulto(false);
                      }}
                      className="rounded-full"
                    >
                      Probar otro código
                    </Button>
                    <Button
                      asChild
                      variant="ghost"
                      size="sm"
                      className="rounded-full"
                    >
                      <Link to="/campos">Hacer una reserva nueva</Link>
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {datos && (
            <div className="space-y-6">
              {/* Indicador de polling activo */}
              {pollingActivo && datos.estado === 'pendiente' && (
                <div className="flex items-center gap-2 text-sm text-teal-700 bg-teal-50 px-4 py-2 rounded-lg border border-teal-200">
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  <span>Verificando estado de pago automáticamente...</span>
                </div>
              )}

              <EstadoReserva
                estado={datos.estado}
                montoTotal={datos.monto_total}
                expiraEn={datos.expira_en}
                codigoSeguimiento={datos.codigo_seguimiento}
                esPiscina={esPiscina}
                mostrarAcciones
              />

              {/* Botón para refrescar manualmente */}
              {datos.estado === 'pendiente' && (
                <div className="text-center">
                  <Button
                    onClick={consultar}
                    variant="outline"
                    size="sm"
                    disabled={cargando}
                    className="rounded-full"
                  >
                    {cargando ? (
                      <>
                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                        Verificando...
                      </>
                    ) : (
                      <>
                        <RefreshCw className="w-4 h-4 mr-2" />
                        Verificar estado ahora
                      </>
                    )}
                  </Button>
                </div>
              )}

              {datos.estado === 'confirmada' && (
                <>
                  <ComprobantePDF datos={datos} />
                  <div className="text-center">
                    <Button asChild variant="outline" className="rounded-full">
                      <Link
                        to={comprobantePath}
                        className="flex items-center gap-2"
                      >
                        Ver comprobante con opción de impresión
                        <ArrowRight className="w-4 h-4" />
                      </Link>
                    </Button>
                  </div>
                </>
              )}
            </div>
          )}

          {/* Estado inicial (no ha consultado aún) */}
          {!yaConsulto && !cargando && !error && !datos && (
            <div className="text-center py-8">
              <p className="text-sm text-slate-500">
                ¿No tenés un código?{' '}
                <Link
                  to="/campos"
                  className="text-teal-600 hover:text-teal-700 font-semibold"
                >
                  Reservá una cancha ahora
                </Link>
              </p>
            </div>
          )}
        </div>
      </div>
    </>
  );
}
