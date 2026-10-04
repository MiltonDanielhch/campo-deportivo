import { useState, useEffect, useCallback } from 'react';
import { Calendar, Clock, MapPin, RefreshCw, Search, UserCheck } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ocupacionService } from '@/services/ocupacionService';
import type { CampoOcupacion, Franja, VerificarCodigoResponse } from '@/types/ocupacion';
import { format, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { toast } from 'sonner';

export default function MisCampos() {
  const [campos, setCampos] = useState<CampoOcupacion[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [fecha, setFecha] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [codigoBusqueda, setCodigoBusqueda] = useState('');
  const [resultadoVerificacion, setResultadoVerificacion] =
    useState<VerificarCodigoResponse['data'] | null>(null);
  const [verificando, setVerificando] = useState(false);
  const [ultimaActualizacion, setUltimaActualizacion] = useState<Date | null>(null);

  const cargarOcupacion = useCallback(async () => {
    try {
      setCargando(true);
      setError(null);
      const response = await ocupacionService.misCampos(fecha);
      setCampos(response.data);
      setUltimaActualizacion(new Date());
    } catch (err: any) {
      const mensaje =
        err.response?.data?.message || 'Error al cargar la ocupación';
      setError(mensaje);
      toast.error(mensaje);
    } finally {
      setCargando(false);
    }
  }, [fecha]);

  useEffect(() => {
    cargarOcupacion();
  }, [cargarOcupacion]);

  // Refresco automático cada 30 segundos
  useEffect(() => {
    const interval = setInterval(() => {
      cargarOcupacion();
    }, 30000);

    return () => clearInterval(interval);
  }, [cargarOcupacion]);

  const handleVerificarCodigo = async () => {
    if (!codigoBusqueda.trim()) {
      toast.error('Ingresá un código de reserva');
      return;
    }

    try {
      setVerificando(true);
      setResultadoVerificacion(null);
      const response = await ocupacionService.verificarCodigo(codigoBusqueda.trim());
      setResultadoVerificacion(response.data);
      toast.success('Código válido');
    } catch (err: any) {
      const mensaje =
        err.response?.data?.message || 'Código no encontrado o no tenés acceso';
      toast.error(mensaje);
      setResultadoVerificacion(null);
    } finally {
      setVerificando(false);
    }
  };

  const handleKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      handleVerificarCodigo();
    }
  };

  const formatTiempoRelativo = (fecha: Date): string => {
    const ahora = new Date();
    const diff = Math.floor((ahora.getTime() - fecha.getTime()) / 1000);

    if (diff < 10) return 'hace un momento';
    if (diff < 60) return `hace ${diff} segundos`;
    if (diff < 3600) return `hace ${Math.floor(diff / 60)} minutos`;
    return `hace ${Math.floor(diff / 3600)} horas`;
  };

  const esFranjaActual = (franja: Franja): boolean => {
    const ahora = new Date();
    const [hInicio, mInicio] = franja.hora_inicio.split(':').map(Number);
    const [hFin, mFin] = franja.hora_fin.split(':').map(Number);

    const inicioMinutos = hInicio * 60 + mInicio;
    const finMinutos = hFin * 60 + mFin;
    const ahoraMinutos = ahora.getHours() * 60 + ahora.getMinutes();

    return ahoraMinutos >= inicioMinutos && ahoraMinutos < finMinutos;
  };

  const obtenerEstiloFranja = (franja: Franja): string => {
    const esActual = esFranjaActual(franja);

    switch (franja.estado) {
      case 'ocupada':
        return esActual
          ? 'bg-red-100 border-red-500 border-2 shadow-lg'
          : 'bg-red-50 border-red-200';
      case 'pendiente':
        return esActual
          ? 'bg-yellow-100 border-yellow-500 border-2 shadow-lg'
          : 'bg-yellow-50 border-yellow-200';
      case 'libre':
        return esActual
          ? 'bg-green-100 border-green-500 border-2 shadow-lg'
          : 'bg-white border-slate-200';
      default:
        return 'bg-white border-slate-200';
    }
  };

  const obtenerEtiquetaEstado = (estado: string): string => {
    switch (estado) {
      case 'ocupada':
        return 'Ocupada';
      case 'pendiente':
        return 'Pendiente';
      case 'libre':
        return 'Libre';
      default:
        return estado;
    }
  };

  const obtenerColorBadge = (estado: string): string => {
    switch (estado) {
      case 'ocupada':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'pendiente':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'libre':
        return 'bg-green-100 text-green-800 border-green-200';
      default:
        return 'bg-slate-100 text-slate-800 border-slate-200';
    }
  };

  return (
    <div className="space-y-6 p-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">Ocupación</h1>
          <p className="text-sm text-muted-foreground">
            Control en sitio de campos asignados
          </p>
        </div>
        {ultimaActualizacion && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <Clock className="size-3" />
            <span>Actualizado {formatTiempoRelativo(ultimaActualizacion)}</span>
          </div>
        )}
      </div>

      {/* Buscador de verificación de código */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <Search className="size-5" />
            Verificar código de reserva
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex gap-2">
            <Input
              placeholder="Ej: RSV-ABC123"
              value={codigoBusqueda}
              onChange={(e) => setCodigoBusqueda(e.target.value.toUpperCase())}
              onKeyPress={handleKeyPress}
              className="flex-1"
            />
            <Button onClick={handleVerificarCodigo} disabled={verificando}>
              {verificando ? 'Verificando...' : 'Verificar'}
            </Button>
          </div>

          {resultadoVerificacion && (
            <Alert className="border-green-200 bg-green-50">
              <AlertDescription className="space-y-2">
                <div className="font-semibold text-green-900">
                  ✓ Reserva válida
                </div>
                <div className="space-y-1 text-sm">
                  <div className="flex items-center gap-2">
                    <MapPin className="size-4 text-green-700" />
                    <span className="font-medium">
                      {resultadoVerificacion.campo_nombre}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Calendar className="size-4 text-green-700" />
                    <span>
                      {format(
                        parseISO(resultadoVerificacion.fecha_reserva),
                        "d 'de' MMMM, yyyy",
                        { locale: es }
                      )}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Clock className="size-4 text-green-700" />
                    <span>
                      {resultadoVerificacion.hora_inicio} -{' '}
                      {resultadoVerificacion.hora_fin}
                    </span>
                  </div>
                  {resultadoVerificacion.asistencia_marcada_en && (
                    <div className="flex items-center gap-2">
                      <UserCheck className="size-4 text-green-700" />
                      <span className="text-xs">
                        Asistencia marcada:{' '}
                        {format(
                          parseISO(resultadoVerificacion.asistencia_marcada_en),
                          'HH:mm'
                        )}
                      </span>
                    </div>
                  )}
                </div>
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>

      {/* Selector de fecha */}
      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2">
          <Calendar className="size-5 text-muted-foreground" />
          <Input
            type="date"
            value={fecha}
            onChange={(e) => setFecha(e.target.value)}
            className="w-auto"
          />
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={cargarOcupacion}
          disabled={cargando}
        >
          <RefreshCw
            className={`size-4 ${cargando ? 'animate-spin' : ''}`}
          />
        </Button>
      </div>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Lista de campos con ocupación */}
      {cargando && campos.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground">
          Cargando ocupación...
        </div>
      ) : campos.length === 0 ? (
        <Card>
          <CardContent className="py-12 text-center text-muted-foreground">
            No hay campos asignados para mostrar.
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-6">
          {campos.map((campo) => (
            <Card key={campo.campo_id}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <MapPin className="size-5" />
                    <span>{campo.campo_nombre}</span>
                  </div>
                  {!campo.abierto && (
                    <Badge variant="outline" className="text-xs">
                      Cerrado hoy
                    </Badge>
                  )}
                </CardTitle>
              </CardHeader>
              <CardContent>
                {!campo.abierto ? (
                  <p className="text-sm text-muted-foreground">
                    Este campo no tiene horario de atención configurado para hoy.
                  </p>
                ) : (
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    {campo.franjas.map((franja, idx) => {
                      const esActual = esFranjaActual(franja);
                      return (
                        <div
                          key={idx}
                          className={`relative rounded-lg border p-3 transition-all ${obtenerEstiloFranja(
                            franja
                          )}`}
                        >
                          <div className="flex flex-col gap-1">
                            <div className="flex items-center justify-between">
                              <span className="text-xs font-mono font-semibold">
                                {franja.hora_inicio.substring(0, 5)}
                              </span>
                              {esActual && (
                                <span className="size-2 rounded-full bg-current animate-pulse" />
                              )}
                            </div>
                            <Badge
                              variant="outline"
                              className={`text-xs ${obtenerColorBadge(
                                franja.estado
                              )}`}
                            >
                              {obtenerEtiquetaEstado(franja.estado)}
                            </Badge>
                            {franja.codigo_reserva && (
                              <div className="text-xs font-mono text-muted-foreground truncate">
                                {franja.codigo_reserva}
                              </div>
                            )}
                            {franja.asistencia_marcada_en && (
                              <div className="flex items-center gap-1 text-xs text-green-700">
                                <UserCheck className="size-3" />
                                <span>
                                  {format(
                                    parseISO(franja.asistencia_marcada_en),
                                    'HH:mm'
                                  )}
                                </span>
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
