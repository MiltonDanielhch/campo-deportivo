import { useState, useEffect } from 'react';
import { Helmet } from 'react-helmet-async';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import FiltrosCampos from '@/components/campo/FiltrosCampos';
import CampoCard from '@/components/campo/CampoCard';
import MapaCampos from '@/components/campo/MapaCampos';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { id: string; nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
}

type VistaModo = 'lista' | 'mapa';

export default function Campos() {
  const [campos, setCampos] = useState<Campo[]>([]);
  const [camposFiltrados, setCamposFiltrados] = useState<Campo[]>([]);
  const [cargando, setCargando] = useState(true);
  const [vista, setVista] = useState<VistaModo>('lista');
  const [filtros, setFiltros] = useState<{ tipoCampoId: string | null; estado: string | null }>({
    tipoCampoId: null,
    estado: null,
  });

  useEffect(() => {
    api
      .get('/public/campos')
      .then((res) => {
        setCampos(res.data.data);
        setCamposFiltrados(res.data.data);
      })
      .catch((err) => console.error('Error al cargar campos:', err))
      .finally(() => setCargando(false));
  }, []);

  useEffect(() => {
    let filtrados = campos;

    if (filtros.tipoCampoId) {
      filtrados = filtrados.filter((c) => c.tipo_campo.id === filtros.tipoCampoId);
    }

    if (filtros.estado) {
      filtrados = filtrados.filter((c) => c.estado === filtros.estado);
    }

    setCamposFiltrados(filtrados);
  }, [campos, filtros]);

  return (
    <>
      <Helmet>
        <title>Campos Deportivos Disponibles - GAD Beni</title>
        <meta
          name="description"
          content="Explora todos los campos deportivos disponibles en Trinidad, Beni. Filtrá por tipo de deporte y ubicación."
        />
      </Helmet>

      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8">
          <h1 className="text-3xl font-bold mb-6">Campos Deportivos</h1>

          <FiltrosCampos onFiltrosChange={setFiltros} />

          <div className="flex gap-2 mb-6">
            <Button
              variant={vista === 'lista' ? 'default' : 'outline'}
              onClick={() => setVista('lista')}
            >
              Lista
            </Button>
            <Button
              variant={vista === 'mapa' ? 'default' : 'outline'}
              onClick={() => setVista('mapa')}
            >
              Mapa
            </Button>
          </div>

          {cargando ? (
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <Card key={i} className="animate-pulse">
                  <CardContent className="pt-6">
                    <div className="h-6 bg-gray-200 rounded w-3/4 mb-4"></div>
                    <div className="h-4 bg-gray-200 rounded w-full mb-2"></div>
                    <div className="h-4 bg-gray-200 rounded w-2/3 mb-4"></div>
                    <div className="h-10 bg-gray-200 rounded"></div>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : vista === 'lista' ? (
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {camposFiltrados.map((campo) => (
                <CampoCard key={campo.id} campo={campo} />
              ))}
            </div>
          ) : (
            <MapaCampos campos={camposFiltrados} />
          )}

          {camposFiltrados.length === 0 && !cargando && (
            <div className="text-center py-12">
              <p className="text-gray-600 text-lg">No se encontraron campos con esos filtros.</p>
            </div>
          )}
        </div>
      </div>
    </>
  );
}