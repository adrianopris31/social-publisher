import { Form, Head } from '@inertiajs/react';
import { Hash, Plus, TextQuote } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import CaptionTemplateController from '@/actions/App/Http/Controllers/CaptionTemplateController';
import { CaptionTemplateDialog } from '@/components/caption-template-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as captionTemplatesIndex } from '@/routes/caption-templates';
import type { CaptionTemplate, CaptionTemplateType } from '@/types';

export default function CaptionTemplatesIndex({
    templates,
}: {
    templates: CaptionTemplate[];
}) {
    return (
        <>
            <Head title="Templates" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title="Templates"
                    description="Reusable captions and hashtag sets. Insert them from the composer with one click."
                />

                <TemplateSection
                    type="caption"
                    title="Captions"
                    description="Calls to action, standard descriptions, links you repeat."
                    icon={TextQuote}
                    templates={templates.filter(
                        (template) => template.type === 'caption',
                    )}
                />
                <TemplateSection
                    type="hashtags"
                    title="Hashtag sets"
                    description="Added on a new line at the end of the caption."
                    icon={Hash}
                    templates={templates.filter(
                        (template) => template.type === 'hashtags',
                    )}
                />
            </div>
        </>
    );
}

function TemplateSection({
    type,
    title,
    description,
    icon: Icon,
    templates,
}: {
    type: CaptionTemplateType;
    title: string;
    description: string;
    icon: LucideIcon;
    templates: CaptionTemplate[];
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        <Icon className="size-4" />
                        {title}
                    </CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <CaptionTemplateDialog
                    defaultType={type}
                    trigger={
                        <Button size="sm" variant="outline">
                            <Plus />
                            New
                        </Button>
                    }
                />
            </CardHeader>
            <CardContent>
                {templates.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Nothing saved yet.
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {templates.map((template) => (
                            <li
                                key={template.id}
                                className="flex items-start justify-between gap-4 p-3"
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {template.name}
                                    </p>
                                    <p className="line-clamp-3 text-sm wrap-anywhere whitespace-pre-line text-muted-foreground">
                                        {template.body}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <CaptionTemplateDialog
                                        template={template}
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Edit
                                            </Button>
                                        }
                                    />
                                    <DeleteDialog template={template} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function DeleteDialog({ template }: { template: CaptionTemplate }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm">
                    Delete
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {template.name}?</DialogTitle>
                <DialogDescription>
                    Posts that already used it keep their text.
                </DialogDescription>

                <Form
                    {...CaptionTemplateController.destroy.form(template.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                Delete
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CaptionTemplatesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Templates',
            href: captionTemplatesIndex(),
        },
    ],
};
