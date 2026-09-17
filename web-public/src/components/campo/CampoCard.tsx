import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Link } from 'react-router-dom';

interface Campo {
  id: string;
  nombre: string;
  tipo_campo: { nombre: string };
  direccion: string;
  estado: string;
  latitud: number;
  longitud: number;
}

interface CampoCardProps {
  campo: Campo;
}

export default function CampoCard({ campo }: CampoCardProps) {
  const esMantenimiento = campo.estado === 'mantenimiento';

  return (
    <Card className={esMantenimiento ? 'opacity-60' : ''}>
      <CardHeader>
        <CardTitle className="flex items-center justify-between">
          <span>{campo.nombre}</span>
          <Badge variant={esMantenimiento ? 'secondary' : 'default'}>{campo.estado}</Badge>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-sm text-gray-600 mb-2">{campo.tipo_campo.nombre}</p>
        <p className="text-sm mb-4">{campo.direccion}</p>
        {!esMantenimiento && (
          <Link
            to={`/campos/${campo.id}`}
            className="inline-block px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition-colors text-sm"
          >
            Ver disponibilidad
          </Link>
        )}
        {esMantenimiento && (
          <p className="text-sm text-orange-600 italic">No disponible temporalmente</p>
        )}
      </CardContent>
    </Card>
  );
}