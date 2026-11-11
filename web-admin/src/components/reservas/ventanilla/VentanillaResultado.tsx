import { Check, Copy, Download, MessageCircle, QrCode } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { QRCodeCanvas } from 'qrcode.react';
import type { ResultadoVentanilla } from './useVentanillaEnvioResultado';

interface Props {
  resultado: ResultadoVentanilla;
  copiado: string | null;
  onCopiar: (texto: string, clave: string) => void | Promise<void>;
  onDescargarQr: () => void;
  onEnviarWhatsapp: () => void;
  onCerrar: () => void;
}

export function VentanillaResultado({
  resultado,
  copiado,
  onCopiar,
  onDescargarQr,
  onEnviarWhatsapp,
  onCerrar,
}: Props) {
  return (
    <div className="space-y-4 py-1">
      <div className="rounded-xl border border-emerald-300/60 bg-emerald-500/10 p-4 text-center dark:border-emerald-700">
        <p className="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">
          Reserva creada
        </p>
        <p className="mt-1 font-mono text-2xl font-bold text-emerald-900 dark:text-emerald-100">
          {resultado.codigo_seguimiento}
        </p>
        <p className="mt-1 text-lg font-bold tabular-nums text-emerald-900 dark:text-emerald-100">
          Bs {resultado.monto_total.toFixed(2)}
        </p>
        <p className="mt-1 text-xs text-emerald-800/80 dark:text-emerald-200/80">
          Monto emitido por SIREB
        </p>
      </div>

      {resultado.referencia_recaudaciones ? (
        <div className="space-y-3 rounded-xl border bg-muted/50 p-4">
          <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Código de pago en ventanilla
          </p>

          {resultado.qr_string && (
            <div className="flex flex-col items-center gap-3 py-2">
              <div className="rounded-xl border bg-white p-3">
                <QRCodeCanvas
                  id="qr-ventanilla"
                  value={resultado.qr_string}
                  size={220}
                  level="M"
                  marginSize={2}
                />
              </div>
              <div className="flex flex-wrap justify-center gap-2">
                <Button variant="outline" size="sm" onClick={onDescargarQr}>
                  <Download className="mr-2 size-4" />
                  Descargar QR
                </Button>
                <Button variant="outline" size="sm" onClick={onEnviarWhatsapp}>
                  <MessageCircle className="mr-2 size-4" />
                  Enviar por WhatsApp
                </Button>
              </div>
            </div>
          )}

          <div className="flex items-center justify-between gap-2">
            <code className="min-w-0 truncate font-mono text-xl font-bold tracking-widest">
              {resultado.referencia_recaudaciones}
            </code>
            <Button
              variant="ghost"
              size="icon"
              className="shrink-0"
              onClick={() => onCopiar(resultado.referencia_recaudaciones!, 'ref')}
            >
              {copiado === 'ref' ? (
                <Check className="size-4 text-green-600" />
              ) : (
                <Copy className="size-4" />
              )}
            </Button>
          </div>

          {resultado.qr_string && (
            <div className="flex items-center justify-between gap-2 pt-1">
              <code className="min-w-0 truncate font-mono text-xs text-muted-foreground">
                {resultado.qr_string}
              </code>
              <Button
                variant="ghost"
                size="icon"
                className="shrink-0"
                onClick={() => onCopiar(resultado.qr_string!, 'qr')}
              >
                {copiado === 'qr' ? (
                  <Check className="size-4 text-green-600" />
                ) : (
                  <Copy className="size-4" />
                )}
              </Button>
            </div>
          )}

          <p className="flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">
            <QrCode className="size-3.5 shrink-0" />
            Pagá con este código en ventanilla de cualquier banco, presentando tu CI/NIT. O escaneá el QR para pagar por Síntesis.
          </p>
        </div>
      ) : (
        <p className="rounded-xl border border-amber-300/60 bg-amber-500/10 p-3 text-sm text-amber-800 dark:border-amber-700 dark:text-amber-200">
          La reserva quedó pendiente: SIREB no respondió y el reintento corre en segundo plano. El código de pago aparece en el detalle en unos minutos.
        </p>
      )}

      <Button className="w-full" onClick={onCerrar}>
        Listo
      </Button>
    </div>
  );
}
