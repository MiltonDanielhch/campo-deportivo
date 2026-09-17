import { useState } from 'react';
import { Helmet } from 'react-helmet-async';
import { Link } from 'react-router-dom';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { api } from '@/lib/api';
import type { EstadoSolicitudPublico } from '@/types/reserva';
import ComprobantePDF from '@/components/comprobante/ComprobantePDF';
import EstadoReserva from '@/components/comprobante/EstadoReserva';

export default function ConsultarEstado() {
  const [codigo, setCodigo] = useState('');
  const [datos, setDatos] = useState<EstadoSolicitudPublico | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(false);

  const consultar = async () => {
    const limpio = codigo.trim();
    if (!limpio) return;

    setCargando(true);
    setError(null);
    setDatos(null);

    try {
      const res = await api.get(`/public/solicitudes-reserva/${limpio}/estado`);
      setDatos(res.data.data);
    } catch (err: any) {
      if (err.response?.status === 404) {
        setError('No se encontró ninguna reserva con ese código.');
      } else {
        setError('Error de conexión. Intentá nuevamente.');
      }
    } finally {
      setCargando(false);
    }
  };

  return (
    <>
      <Helmet>
        <title>Consultar estado de mi reserva - Canchas GAD Beni</title>
        <meta
          name="description"
          content="Consultá el estado de tu reserva de cancha con tu código de seguimiento."
        />
      </Helmet>

      <div className="min-h-screen bg-gray-50">
        <div className="container mx-auto px-4 py-8 max-w-2xl">
          <h1 className="text-3xl font-bold mb-6">Consultar mi reserva</h1>

          <Card className="mb-6">
            <CardContent className="pt-6">
              <Label htmlFor="codigo">Código de seguimiento</Label>
              <div className="flex gap-2 mt-2">
                <Input
                  id="codigo"
                  value={codigo}
                  onChange={(e) => setCodigo(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && consultar()}
                  placeholder="RES-20260916-ABC123"
                  className="uppercase font-mono"
                  disabled={cargando}
                />
                <Button onClick={consultar} disabled={cargando}>
                  <Search className="w-4 h-4 mr-2" />
                  Consultar
                </Button>
              </div>
              <p className="text-xs text-gray-500 mt-2">
                Lo recibiste al crear tu solicitud (formato RES-YYYYMMDD-XXXXXX).
              </p>
            </CardContent>
          </Card>

          {error && (
            <Card className="border-red-200 bg-red-50">
              <CardContent className="pt-6 text-center text-red-700">
                {error}
              </CardContent>
            </Card>
          )}

          {datos && (
            <>
              <EstadoReserva
                estado={datos.estado}
                montoTotal={datos.monto_total}
                expiraEn={datos.expira_en}
              />
              {datos.estado === 'confirmada' && (
                <div className="mt-6">
                  <ComprobantePDF datos={datos} />
                  <div className="text-center mt-4">
                    <Button variant="outline" asChild>
                      <Link to={`/comprobante/${datos.codigo_seguimiento}`}>
                        Ver comprobante con opción de impresión
                      </Link>
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </>
  );
}
