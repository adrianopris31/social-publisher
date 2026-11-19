import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import CaptionTemplateController from '@/actions/App/Http/Controllers/CaptionTemplateController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { CaptionTemplate, CaptionTemplateType } from '@/types';

type TemplateForm = {
    type: CaptionTemplateType;
    name: string;
    body: string;
};

/**
 * Creates or edits a caption template or hashtag set. Saving keeps the current page's state,
 * so a post being written in the composer is not lost.
 */
export function CaptionTemplateDialog({
    template,
    defaultType = 'caption',
    defaultBody = '',
    trigger,
}: {
    /** The template to edit; omit to create one. */
    template?: CaptionTemplate;
    defaultType?: CaptionTemplateType;
    defaultBody?: string;
    trigger: ReactNode;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm<TemplateForm>({
        type: template?.type ?? defaultType,
        name: template?.name ?? '',
        body: template?.body ?? defaultBody,
    });

    function open(nextIsOpen: boolean) {
        if (nextIsOpen) {
            form.clearErrors();
            form.setData({
                type: template?.type ?? defaultType,
                name: template?.name ?? '',
                body: template?.body ?? defaultBody,
            });
        }

        setIsOpen(nextIsOpen);
    }

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setIsOpen(false),
        };

        if (template) {
            form.submit(CaptionTemplateController.update(template.id), options);
        } else {
            form.submit(CaptionTemplateController.store(), options);
        }
    }

    const isHashtags = form.data.type === 'hashtags';

    return (
        <Dialog open={isOpen} onOpenChange={open}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {template ? `Edit ${template.name}` : 'New template'}
                </DialogTitle>
                <DialogDescription>
                    Insert it into any caption from the composer. Posts that
                    already used it keep their own text.
                </DialogDescription>

                <form onSubmit={submit} className="grid gap-4">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        size="sm"
                        value={form.data.type}
                        onValueChange={(type) =>
                            type &&
                            form.setData('type', type as CaptionTemplateType)
                        }
                        className="justify-start"
                    >
                        <ToggleGroupItem value="caption" className="px-3">
                            Caption
                        </ToggleGroupItem>
                        <ToggleGroupItem value="hashtags" className="px-3">
                            Hashtag set
                        </ToggleGroupItem>
                    </ToggleGroup>

                    <div className="grid gap-2">
                        <Label htmlFor="template-name">Name</Label>
                        <Input
                            id="template-name"
                            value={form.data.name}
                            maxLength={60}
                            placeholder={
                                isHashtags ? 'Gaming tags' : 'Follow for more'
                            }
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="template-body">
                            {isHashtags ? 'Hashtags' : 'Text'}
                        </Label>
                        <Textarea
                            id="template-body"
                            value={form.data.body}
                            rows={isHashtags ? 3 : 5}
                            placeholder={
                                isHashtags
                                    ? '#gaming #shorts #funny'
                                    : 'Follow for a new clip every day!'
                            }
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                        />
                        {isHashtags && (
                            <p className="text-xs text-muted-foreground">
                                Separate with spaces, commas or new lines; the #
                                is added for you. Up to 30, the Instagram limit.
                            </p>
                        )}
                        <InputError message={form.errors.body} />
                        <InputError message={form.errors.type} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            {template ? 'Save changes' : 'Save template'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
