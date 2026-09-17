import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Helmet } from 'react-helmet-async';
import { ArrowLeft, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { api } from '@/lib/api';
import SelectorFechas from '@/components/campo/SelectorFechas';
import GrillaDisponibilidad from '@/components/campo/GrillaDisponibilidad';
import BarraCarrito from '@/components/campo/BarraCarrito';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
  tarifa_vigente: { precio_por_hora: number } | null;
}

interface Disponibilidad {
  abierto: boolean;
  bloques: Array<{
    hora_inicio: string;
    hora_fin: string;
    estado: 'libre' | 'ocupado' | 'bloqueada_temporal';
  }>;
}

export default function CampoDetalle() {
  const { id } = useParams<{ id: string }>();
  const [campo, setCampo] = useState<Campo | null>(null);
  const [disponibilidad, setDisponibilidad] = useState<Disponibilidad | null>(null);
  const [fecha, setFecha] = useState<string>(() => new Date().toISOString().split('T')[0]);
  const [cargandoCampo, setCargandoCampo] = useState(true);
  const [cargandoDisponibilidad, setCargandoDisponibilidad] = useState(true);

  // Cargar info del campo
  useEffect(() => {
    if (!id) return;
    api
      .get(`/public/campos/${id}`)
      .then((res) => setCampo(res.data.data))
      .catch((err) => console.error('Error al cargar campo:', err))
      .finally(() => setCargandoCampo(false));
  }, [id]);

  // Cargar disponibilidad cada vez que cambia fecha o campo
  useEffect(() => {
    if (!id || !campo) return;
    setCargandoDisponibilidad(true);
    api
      .get(`/public/campos/${id}/disponibilidad`, { params: { fecha } })
      .then((res) => setDisponibilidad(res.data.data))
      .catch((err) => console.error('Error al cargar disponibilidad:', err))
      .finally(() => setCargandoDisponibilidad(false));
  }, [id, campo, fecha]);

  // Refresco automático cada 45 segundos
  useEffect(() => {
    if (!id || !campo) return;
    const interval = setInterval(() => {
      api
        .get(`/public/campos/${id}/disponibilidad`, { params: { fecha } })
        .then((res) => setDisponibilidad(res.data.data))
        .catch(() => {/* silencioso en refresco */});
    }, 45000);
    return () => clearInterval(interval);
  }, [id, campo, fecha]);

  if (cargandoCampo) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="animate-pulse">
          <div className="h-8 bg-gray-200 rounded w-1/3 mb-4"></div>
          <div className="h-4 bg-gray-200 rounded w-1/2 mb-8"></div>
          <div className="h-12 bg-gray-200 rounded mb-6"></div>
          <div className="grid grid-cols-4 gap-2">
            {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
              <div key={i} className="h-16 bg-gray-200 rounded"></div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!campo) {
    return (
      <div className="container mx-auto px-4 py-8 text-center">
        <h1 className="text-2xl font-bold mb-4">Campo no encontrado</h1>
        <Button asChild>
          <Link to="/campos">Volver al listado</Link>
        </Button>
      </div>
    );
  }

  const esMantenimiento = campo.estado === 'mantenimiento';
  const precioHora = campo.tarifa_vigente?.precio_por_hora ?? null;

  return (
    <>
      <Helmet>
        <title>{campo.nombre} - Canchas Deportivas GAD Beni</title>
        <meta
          name="description"
          content={`Reserva ${campo.nombre} (${campo.tipo_campo.nombre}) en ${campo.direccion}. Disponible para reserva online.`}
        />
        <meta property="og:title" content={campo.nombre} />
        <meta
          property="og:description"
          content={`${campo.tipo_campo.nombre} en ${campo.direccion}`}
        />
      </Helmet>

      <div className="min-h-screen bg-gray-50 pb-24">
        <div className="container mx-auto px-4 py-8">
          <Button variant="ghost" asChild className="mb-4">
            <Link to="/campos">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Volver al listado
            </Link>
          </Button>

          <Card className="mb-6">
            <CardHeader>
              <CardTitle className="flex items-center justify-between flex-wrap gap-2">
                <span className="text-2xl">{campo.nombre}</span>
                <Badge variant={esMantenimiento ? 'secondary' : 'default'}>
                  {campo.estado}
                </Badge>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-gray-600 mb-2">{campo.tipo_campo.nombre}</p>
              <p className="flex items-center gap-1 text-sm text-gray-700 mb-2">
                <MapPin className="w-4 h-4" />
                {campo.direccion}
              </p>
              {precioHora !== null && (
                <p className="text-lg font-semibold text-green-700">
                  Bs {precioHora.toFixed(2)} por hora
                </p>
              )}
              {precioHora === null && (
                <p className="text-sm text-orange-600">Sin tarifa definida</p>
              )}
            </CardContent>
          </Card>

          {esMantenimiento ? (
            <div className="text-center py-12 bg-orange-50 rounded-lg">
              <p className="text-lg text-orange-700">
                Este campo está en mantenimiento y no está disponible para reserva.
              </p>
            </div>
          ) : (
            <>
              <h2 className="text-xl font-semibold mb-4">Elegí fecha y horario</h2>
              <SelectorFechas fechaSeleccionada={fecha} onFechaChange={setFecha} />

              {cargandoDisponibilidad ? (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                  {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                    <div key={i} className="h-16 bg-gray-200 rounded animate-pulse"></div>
                  ))}
                </div>
              ) : disponibilidad && disponibilidad.abierto ? (
                <GrillaDisponibilidad
                  bloques={disponibilidad.bloques}
                  campoId={campo.id}
                  campoNombre={campo.nombre}
                  fecha={fecha}
                  precioPorHora={precioHora}
                />
              ) : (
                <div className="text-center py-8 bg-gray-100 rounded-lg">
                  <p className="text-gray-600">
                    Este campo está cerrado el día seleccionado.
                  </p>
                </div>
              )}

              <div className="mt-6 p-4 bg-blue-50 rounded-lg text-sm text-blue-900">
                <p className="font-semibold mb-1">💡 Consejo</p>
                <p>
                  Podés seleccionar múltiples franjas de una vez. La grilla se
                  actualiza automáticamente cada 45 segundos.
                </p>
              </div>
            </>
          )}
        </div>

        <BarraCarrito />
      </div>
    </>
  );
}