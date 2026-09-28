import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { User, Phone, CreditCard as IdCard } from 'lucide-react';
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
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center gap-3 p-5 border-b border-slate-100 bg-slate-50/50">
        <div className="w-10 h-10 rounded-xl bg-teal-100 flex items-center justify-center">
          <User className="w-5 h-5 text-teal-700" />
        </div>
        <div>
          <h2 className="text-lg font-bold tracking-tight">
            Datos del solicitante
          </h2>
          <p className="text-xs text-slate-500">
            Necesarios para emitir el comprobante
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="p-5 space-y-5">
        {/* Nombre */}
        <div>
          <Label htmlFor="nombre_pagador" className="flex items-center gap-1.5 mb-2">
            <User className="w-3.5 h-3.5 text-slate-400" />
            <span>
              Nombre completo <span className="text-red-500">*</span>
            </span>
          </Label>
          <Input
            id="nombre_pagador"
            {...register('nombre_pagador')}
            placeholder="Juan Pérez"
            disabled={cargando}
            className={errors.nombre_pagador ? 'border-red-300' : ''}
          />
          {errors.nombre_pagador && (
            <p className="text-xs text-red-600 mt-1.5 flex items-center gap-1">
              <span className="w-1 h-1 rounded-full bg-red-500" />
              {errors.nombre_pagador.message}
            </p>
          )}
        </div>

        {/* Teléfono */}
        <div>
          <Label htmlFor="telefono_pagador" className="flex items-center gap-1.5 mb-2">
            <Phone className="w-3.5 h-3.5 text-slate-400" />
            <span>
              Teléfono <span className="text-red-500">*</span>
            </span>
          </Label>
          <Input
            id="telefono_pagador"
            {...register('telefono_pagador')}
            placeholder="70000000"
            maxLength={8}
            disabled={cargando}
            className={errors.telefono_pagador ? 'border-red-300' : ''}
          />
          {errors.telefono_pagador && (
            <p className="text-xs text-red-600 mt-1.5 flex items-center gap-1">
              <span className="w-1 h-1 rounded-full bg-red-500" />
              {errors.telefono_pagador.message}
            </p>
          )}
        </div>

        {/* CI/NIT */}
        <div>
          <Label htmlFor="ci_nit_pagador" className="flex items-center gap-1.5 mb-2">
            <IdCard className="w-3.5 h-3.5 text-slate-400" />
            CI/NIT <span className="text-slate-400 text-xs">(opcional)</span>
          </Label>
          <Input
            id="ci_nit_pagador"
            {...register('ci_nit_pagador')}
            placeholder="1234567"
            disabled={cargando}
          />
        </div>

        <Button
          type="submit"
          className="w-full h-12 rounded-xl font-semibold shadow-md shadow-teal-500/20"
          disabled={cargando}
        >
          {cargando ? 'Creando reserva…' : 'Continuar al pago'}
        </Button>
      </form>
    </div>
  );
}
