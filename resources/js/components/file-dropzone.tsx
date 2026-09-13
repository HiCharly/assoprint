import { FileText, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    /** Nom du champ envoyé au serveur. */
    name: string;
    accept?: string;
    /** Taille maximale acceptée par le serveur, pour prévenir avant l'envoi. */
    maxSizeMb: number;
    onFileChange?: (file: File | null) => void;
};

function formatSize(bytes: number): string {
    const megabytes = bytes / 1024 / 1024;

    return megabytes < 0.1
        ? `${Math.max(1, Math.round(bytes / 1024))} Ko`
        : `${megabytes.toFixed(1)} Mo`;
}

/**
 * Zone de dépôt d'un fichier, par glisser-déposer ou par sélection.
 *
 * Le champ `<input type="file">` reste présent, simplement masqué : c'est lui
 * que le navigateur envoie avec le formulaire. Un fichier déposé lui est
 * assigné, plutôt que gardé dans l'état React — l'envoi reste ainsi un envoi de
 * formulaire ordinaire, et continue de fonctionner sans JavaScript actif.
 */
export default function FileDropzone({
    name,
    accept = 'application/pdf,.pdf',
    maxSizeMb,
    onFileChange,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);

    const select = (files: FileList | null) => {
        const selected = files?.[0] ?? null;

        setFile(selected);
        onFileChange?.(selected);
    };

    const clear = () => {
        if (inputRef.current) {
            inputRef.current.value = '';
        }

        select(null);
    };

    const tooBig = file !== null && file.size > maxSizeMb * 1024 * 1024;
    const wrongType =
        file !== null &&
        file.type !== 'application/pdf' &&
        !file.name.toLowerCase().endsWith('.pdf');

    return (
        <div className="grid gap-2">
            <label
                htmlFor={name}
                onDragOver={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setIsDragging(false);

                    if (
                        inputRef.current === null ||
                        event.dataTransfer.files.length === 0
                    ) {
                        return;
                    }

                    // Le fichier déposé est assigné au champ lui-même : c'est
                    // ce qui permet au formulaire de partir normalement.
                    inputRef.current.files = event.dataTransfer.files;
                    select(event.dataTransfer.files);
                }}
                className={cn(
                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors',
                    'hover:bg-accent/50 focus-within:ring-ring focus-within:ring-2 focus-within:ring-offset-2',
                    isDragging
                        ? 'border-primary bg-accent'
                        : 'border-muted-foreground/25',
                )}
            >
                {file === null ? (
                    <>
                        <Upload className="text-muted-foreground size-6" />
                        <span className="text-sm font-medium">
                            Glissez votre PDF ici
                        </span>
                        <span className="text-muted-foreground text-xs">
                            ou cliquez pour le choisir — {maxSizeMb} Mo maximum
                        </span>
                    </>
                ) : (
                    <>
                        <FileText className="text-muted-foreground size-6" />
                        <span className="max-w-full truncate text-sm font-medium">
                            {file.name}
                        </span>
                        <span className="text-muted-foreground text-xs">
                            {formatSize(file.size)} — cliquez pour changer
                        </span>
                    </>
                )}
            </label>

            <input
                id={name}
                ref={inputRef}
                type="file"
                name={name}
                accept={accept}
                className="sr-only"
                onChange={(event) => select(event.target.files)}
            />

            {file !== null && (
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs">
                        {tooBig && (
                            <span className="text-destructive">
                                Ce fichier dépasse {maxSizeMb} Mo et sera
                                refusé.
                            </span>
                        )}
                        {!tooBig && wrongType && (
                            <span className="text-destructive">
                                Seuls les fichiers PDF peuvent être imprimés.
                            </span>
                        )}
                    </p>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={clear}
                    >
                        <X />
                        Retirer
                    </Button>
                </div>
            )}
        </div>
    );
}
