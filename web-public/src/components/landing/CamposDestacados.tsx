import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
}

export default function CamposDestacados() {
  const [campos, setCampos] = useState<Campo[]>([]);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    api
      .get('/public/campos')
      .then((res) => {
        setCampos(res.data.data.slice(0, 3)); // Solo los 3 primeros
      })
      .catch((err) => {
        console.error('Error al cargar campos destacados:', err);
      })
      .finally(() => setCargando(false));
  }, []);

  if (cargando) {
    return (
      <section className="py-16">
        <div className="container mx-auto px-4">
          <h2 className="text-3xl font-bold text-center mb-12">Campos destacados</h2>
          <div className="grid md:grid-cols-3 gap-6">
            {[1, 2, 3].map((i) => (
              <Card key={i} className="animate-pulse">
                <CardHeader>
                  <div className="h-6 bg-gray-200 rounded w-3/4"></div>
                </CardHeader>
                <CardContent>
                  <div className="h-4 bg-gray-200 rounded w-full mb-2"></div>
                  <div className="h-4 bg-gray-200 rounded w-2/3"></div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="py-16">
      <div className="container mx-auto px-4">
        <h2 className="text-3xl font-bold text-center mb-12">Campos destacados</h2>
        <div className="grid md:grid-cols-3 gap-6 mb-8">
          {campos.map((campo) => (
            <Card key={campo.id}>
              <CardHeader>
                <CardTitle className="flex items-center justify-between">
                  <span>{campo.nombre}</span>
                  <Badge variant={campo.estado === 'activo' ? 'default' : 'secondary'}>
                    {campo.estado}
                  </Badge>
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-sm text-gray-600 mb-2">{campo.tipo_campo.nombre}</p>
                <p className="text-sm">{campo.direccion}</p>
              </CardContent>
            </Card>
          ))}
        </div>
        <div className="text-center">
          <Button asChild>
            <Link to="/campos">Ver todos los campos</Link>
          </Button>
        </div>
      </div>
    </section>
  );
}
