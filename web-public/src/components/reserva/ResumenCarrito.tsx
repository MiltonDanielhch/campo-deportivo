import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import { useCarritoStore } from '@/store/carritoStore';

export default function ResumenCarrito() {
  const { franjas, quitarFranja, calcularTotal } = useCarritoStore();

  if (franjas.length === 0) return null;

  return (
    <Card className="mb-6">
      <CardHeader>
        <CardTitle>Franjas seleccionadas</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-3 mb-4">
          {franjas.map((franja, i) => (
            <div
              key={`${franja.campoId}-${franja.fecha}-${franja.horaInicio}`}
              className="flex items-start justify-between gap-4 pb-3 border-b last:border-0"
            >
              <div className="flex-1">
                <p className="font-semibold">{franja.campoNombre}</p>
                <p className="text-sm text-gray-600">
                  {franja.fecha} · {franja.horaInicio.slice(0, 5)} - {franja.horaFin.slice(0, 5)}
                </p>
                {franja.precio !== null && (
                  <p className="text-sm text-green-700 font-medium">
                    Bs {franja.precio.toFixed(2)}
                  </p>
                )}
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => quitarFranja(franja)}
                className="text-red-600 hover:text-red-700 hover:bg-red-50"
              >
                <X className="w-4 h-4" />
              </Button>
            </div>
          ))}
        </div>
        <div className="pt-3 border-t">
          <div className="flex justify-between items-center text-lg font-bold">
            <span>Total:</span>
            <span className="text-green-700">Bs {calcularTotal().toFixed(2)}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}