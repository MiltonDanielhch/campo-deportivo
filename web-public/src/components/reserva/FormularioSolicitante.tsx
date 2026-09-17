import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

const schema = z.object({
  nombre_pagador: z.string().min(3, 'El nombre debe tener al menos 3 caracteres'),
  telefono_pagador: z
    .string()
    .regex(/^[0-9]{8}$/, 'El teléfono debe tener 8 dígitos'),
  ci_nit_pagador: z.string().optional(),
});

type FormData = z.infer<typeof schema>;

interface FormularioSolicitanteProps {
  onSubmit: (data: FormData) => void;
  cargando: boolean;
}

export default function FormularioSolicitante({
  onSubmit,
  cargando,
}: FormularioSolicitanteProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Datos del solicitante</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <Label htmlFor="nombre_pagador">Nombre completo *</Label>
            <Input
              id="nombre_pagador"
              {...register('nombre_pagador')}
              placeholder="Juan Pérez"
              disabled={cargando}
            />
            {errors.nombre_pagador && (
              <p className="text-sm text-red-600 mt-1">{errors.nombre_pagador.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="telefono_pagador">Teléfono *</Label>
            <Input
              id="telefono_pagador"
              {...register('telefono_pagador')}
              placeholder="70000000"
              disabled={cargando}
            />
            {errors.telefono_pagador && (
              <p className="text-sm text-red-600 mt-1">{errors.telefono_pagador.message}</p>
            )}
          </div>

          <div>
            <Label htmlFor="ci_nit_pagador">CI/NIT (opcional)</Label>
            <Input
              id="ci_nit_pagador"
              {...register('ci_nit_pagador')}
              placeholder="1234567"
              disabled={cargando}
            />
          </div>

          <Button type="submit" className="w-full" disabled={cargando}>
            {cargando ? 'Creando reserva...' : 'Confirmar y pagar'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}