import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { User, Phone, CreditCard as IdCard } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';

const schema = z.object({
  nombre_pagador: z
    .string()
    .trim()
    .min(3, 'El nombre o razón social debe tener al menos 3 caracteres')
    .max(150, 'El nombre o razón social no puede superar los 150 caracteres'),

  telefono_pagador: z
    .string()
    .trim()
    .regex(/^[0-9]{8}$/, 'El teléfono debe tener 8 dígitos'),

  // SIREB v1: CI/NIT obligatorio para crear liquidaciones.
  // Puede ser CI de persona natural o NIT de asociación/empresa.
  ci_nit_pagador: z
    .string()
    .trim()
    .min(6, 'El CI/NIT debe tener al menos 6 caracteres')
    .max(20, 'El CI/NIT no puede superar los 20 caracteres')
    .regex(
      /^[\d\-\.]+$/,
      'El CI/NIT solo puede contener números, guiones y puntos',
    ),
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
            Datos del pagador
          </h2>
          <p className="text-xs text-slate-500">
            Necesarios para emitir el comprobante y la orden de cobro
          </p>
        </div>
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="p-5 space-y-5">
        {/* Nombre o razón social */}
        <div>
          <Label htmlFor="nombre_pagador" className="flex items-center gap-1.5 mb-2">
            <User className="w-3.5 h-3.5 text-slate-400" />
            <span>
              Nombre o razón social <span className="text-red-500">*</span>
            </span>
          </Label>
          <Input
            id="nombre_pagador"
            {...register('nombre_pagador')}
            placeholder="Ej.: Juan Pérez o Asociación Deportiva San José"
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

        {/* CI / NIT / identificación de la asociación */}
        <div>
          <Label htmlFor="ci_nit_pagador" className="flex items-center gap-1.5 mb-2">
            <IdCard className="w-3.5 h-3.5 text-slate-400" />
            <span>
              CI / NIT / identificación de la asociación{' '}
              <span className="text-red-500">*</span>
            </span>
          </Label>
          <Input
            id="ci_nit_pagador"
            {...register('ci_nit_pagador')}
            placeholder="Ej.: 1234567 o 1234567890"
            disabled={cargando}
            className={errors.ci_nit_pagador ? 'border-red-300' : ''}
          />
          {errors.ci_nit_pagador && (
            <p className="text-xs text-red-600 mt-1.5 flex items-center gap-1">
              <span className="w-1 h-1 rounded-full bg-red-500" />
              {errors.ci_nit_pagador.message}
            </p>
          )}
          <p className="text-xs text-slate-500 mt-1.5">
            Necesario para emitir la orden de cobro en el banco. Puede ser CI de
            persona natural o NIT de asociación/empresa.
          </p>
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
