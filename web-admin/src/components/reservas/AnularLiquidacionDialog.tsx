import { useState } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, Loader2 } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';

interface AnularLiquidacionDialogProps {
  solicitudId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess: () => void;
}

export default function AnularLiquidacionDialog({
  solicitudId,
  open,
  onOpenChange,
  onSuccess,
}: AnularLiquidacionDialogProps) {
  const [motivo, setMotivo] = useState('');
  const [cargando, setCargando] = useState(false);

  const handleConfirmar = async () => {
    if (motivo.trim().length < 10) {
      toast.error('El motivo debe tener al menos 10 caracteres');
      return;
    }

    setCargando(true);
    try {
      await solicitudesReservaService.anularLiquidacion(solicitudId, motivo.trim());
      toast.success('Liquidación anulada correctamente');
      setMotivo('');
      onSuccess();
    } catch (error: any) {
      const data = error.response?.data;
      if (data?.codigo_error === 'LIQUIDACION_NO_ANULABLE') {
        toast.error(
          'No se puede anular: la liquidación ya tiene pago registrado en el banco.',
        );
      } else {
        toast.error(
          data?.message || 'Error al anular la liquidación. Intenta nuevamente.',
        );
      }
    } finally {
      setCargando(false);
    }
  };

  const handleCancel = () => {
    setMotivo('');
    onOpenChange(false);
  };

  return (
    <AlertDialog open={open} onOpenChange={(v) => !v && handleCancel()}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-red-600" />
            Anular liquidación
          </AlertDialogTitle>
          <AlertDialogDescription>
            Esta acción anulará la liquidación en el sistema SIREB y marcará la
            solicitud como cancelada. Solo es posible si el ciudadano aún no ha
            pagado en ventanilla.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div className="space-y-2 py-4">
          <Label htmlFor="motivo">
            Motivo de anulación <span className="text-red-500">*</span>
          </Label>
          <Input
            id="motivo"
            value={motivo}
            onChange={(e) => setMotivo(e.target.value)}
            placeholder="Explica el motivo (mínimo 10 caracteres)"
            disabled={cargando}
            autoFocus
          />
          <p className="text-xs text-muted-foreground">
            {motivo.length}/10 caracteres mínimos. Quedará registrado en la auditoría.
          </p>
        </div>

        <AlertDialogFooter>
          <AlertDialogCancel disabled={cargando} onClick={handleCancel}>
            Cancelar
          </AlertDialogCancel>
          <Button
            variant="destructive"
            onClick={handleConfirmar}
            disabled={cargando || motivo.trim().length < 10}
          >
            {cargando ? (
              <>
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                Anulando...
              </>
            ) : (
              'Confirmar anulación'
            )}
          </Button>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
