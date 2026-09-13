import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PrintOptions } from '@/types';

type Errors = Partial<Record<string, string>>;

type Props = {
    options: PrintOptions;
    errors: Errors;
    defaults?: {
        copies?: number;
        duplex?: string;
        colorMode?: string;
    };
};

/**
 * Réglages communs au dépôt d'un document, à la duplication et à la relance :
 * les trois formulaires proposent exactement les mêmes choix.
 */
export default function PrintSettingsFields({
    options,
    errors,
    defaults,
}: Props) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="copies">Nombre de copies</Label>

                <Input
                    id="copies"
                    name="copies"
                    type="number"
                    min={1}
                    max={options.maxCopies}
                    required
                    defaultValue={defaults?.copies ?? 1}
                    className="max-w-32"
                />

                <p className="text-muted-foreground text-xs">
                    Maximum {options.maxCopies} copies par impression.
                </p>

                <InputError message={errors.copies} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="duplex">Recto / verso</Label>

                <Select
                    name="duplex"
                    defaultValue={defaults?.duplex ?? options.duplex[0]?.value}
                >
                    <SelectTrigger id="duplex" className="max-w-xs">
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        {options.duplex.map((choice) => (
                            <SelectItem key={choice.value} value={choice.value}>
                                {choice.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <InputError message={errors.duplex} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="color_mode">Couleur</Label>

                <Select
                    name="color_mode"
                    defaultValue={
                        defaults?.colorMode ?? options.colorModes[0]?.value
                    }
                >
                    <SelectTrigger id="color_mode" className="max-w-xs">
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        {options.colorModes.map((choice) => (
                            <SelectItem key={choice.value} value={choice.value}>
                                {choice.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <InputError message={errors.color_mode} />
            </div>
        </>
    );
}
