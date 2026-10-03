import { useState, useEffect } from 'react';
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import { MapPin, Calendar, RefreshCw } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { ocupacionService } from '@/services/ocupacionService';
import type { CampoMapaGlobal } from '@/types/mapaGlobal';
import { format } from 'date-fns';
import { toast } from 'sonner';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix para iconos de Leaflet en Vite
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

export default function MapaGlobal() {
  const [campos, setCampos] = useState<CampoMapaGlobal[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [fecha, setFecha] = useState(format(new Date(), 'yyyy-MM-dd'));

  const cargarMapa = async () => {
    try {
      setCargando(true);
      setError(null);
      const response = await ocupacionService.mapaGlobal(fecha);
      setCampos(response.data);
    } catch (err: any) {
      const mensaje =
        err.response?.status === 403
          ? 'No tenés permisos para ver el mapa global'
          : err.response?.data?.message || 'Error al cargar el mapa';
      setError(mensaje);
      toast.error(mensaje);
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarMapa();
  }, [fecha]);

  const obtenerColorMarcador = (campo: CampoMapaGlobal): string => {
    if (campo.estado_operativo === 'mantenimiento') {
      return '#f59e0b'; // amber
    }

    if (!campo.vinculacion_sireb.vinculado) {
      return '#6b7280'; // gray
    }

    const ocupacion = campo.resumen_ocupacion.porcentaje_ocupacion;

    if (ocupacion === 0) return '#10b981'; // green
    if (ocupacion < 50) return '#14b8a6'; // teal
    return '#ef4444'; // red
  };

  // Centro del mapa: Trinidad, Beni (aproximado)
  const centro = [-14.8333, -64.9];

  if (error && error.includes('permisos')) {
    return (
      <div className="p-6">
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Mapa Global</h1>
        <p className="text-sm text-muted-foreground">
          Ocupación de todos los campos del sistema
        </p>
      </div>

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
          onClick={cargarMapa}
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

      {/* Mapa */}
      <Card>
        <CardContent className="p-0">
          <div className="h-[600px] w-full">
            {cargando ? (
              <div className="flex h-full items-center justify-center text-muted-foreground">
                Cargando mapa...
              </div>
            ) : (
              <MapContainer
                center={centro as [number, number]}
                zoom={13}
                className="h-full w-full"
              >
                <TileLayer
                  attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {campos.map((campo) => (
                  <Marker
                    key={campo.campo_id}
                    position={[campo.latitud, campo.longitud]}
                    icon={L.divIcon({
                      className: 'custom-marker',
                      html: `<div style="background-color: ${obtenerColorMarcador(campo)}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
                      iconSize: [24, 24],
                      iconAnchor: [12, 12],
                    })}
                  >
                    <Popup>
                      <div className="space-y-2 min-w-[200px]">
                        <h3 className="font-semibold text-sm">
                          {campo.campo_nombre}
                        </h3>
                        <p className="text-xs text-muted-foreground">
                          {campo.direccion}
                        </p>

                        <div className="space-y-1">
                          <Badge
                            variant="outline"
                            className={
                              campo.estado_operativo === 'activo'
                                ? 'bg-green-100 text-green-800'
                                : 'bg-amber-100 text-amber-800'
                            }
                          >
                            {campo.estado_operativo}
                          </Badge>

                          {campo.vinculacion_sireb.vinculado ? (
                            <Badge variant="outline" className="bg-blue-100 text-blue-800">
                              SIREB: {campo.vinculacion_sireb.servicio_sireb_codigo}
                            </Badge>
                          ) : (
                            <Badge variant="outline" className="bg-gray-100 text-gray-800">
                              Sin vínculo SIREB
                            </Badge>
                          )}
                        </div>

                        <div className="pt-2 border-t space-y-1 text-xs">
                          <div className="flex justify-between">
                            <span>Ocupación:</span>
                            <span className="font-semibold">
                              {campo.resumen_ocupacion.porcentaje_ocupacion}%
                            </span>
                          </div>
                          <div className="flex justify-between">
                            <span>Franjas ocupadas:</span>
                            <span>
                              {campo.resumen_ocupacion.franjas_ocupadas} /{' '}
                              {campo.resumen_ocupacion.franjas_totales}
                            </span>
                          </div>
                          {campo.resumen_ocupacion.ocupado_ahora && (
                            <div className="flex items-center gap-1 text-red-600 font-semibold">
                              <MapPin className="size-3" />
                              Ocupado ahora
                            </div>
                          )}
                        </div>
                      </div>
                    </Popup>
                  </Marker>
                ))}
              </MapContainer>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Leyenda */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Leyenda</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div className="flex items-center gap-2">
              <div className="size-4 rounded-full bg-green-500 border-2 border-white shadow"></div>
              <span>Libre (0%)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="size-4 rounded-full bg-teal-500 border-2 border-white shadow"></div>
              <span>Ocupación media (&lt;50%)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="size-4 rounded-full bg-red-500 border-2 border-white shadow"></div>
              <span>Alta ocupación (≥50%)</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="size-4 rounded-full bg-amber-500 border-2 border-white shadow"></div>
              <span>En mantenimiento</span>
            </div>
            <div className="flex items-center gap-2">
              <div className="size-4 rounded-full bg-gray-500 border-2 border-white shadow"></div>
              <span>Sin vínculo SIREB</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
