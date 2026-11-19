import { Link } from '@inertiajs/react';
import { BookmarkPlus, Hash, Settings2, TextQuote } from 'lucide-react';
import { CaptionTemplateDialog } from '@/components/caption-template-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { index as captionTemplatesIndex } from '@/routes/caption-templates';
import type { CaptionTemplate } from '@/types';

/**
 * Toolbar above a caption field: insert a saved caption or hashtag set, or save the current text.
 */
export function CaptionTemplateMenu({
    templates,
    currentCaption,
    onInsert,
}: {
    templates: CaptionTemplate[];
    currentCaption: string;
    onInsert: (template: CaptionTemplate) => void;
}) {
    const captions = templates.filter(
        (template) => template.type === 'caption',
    );
    const hashtagSets = templates.filter(
        (template) => template.type === 'hashtags',
    );

    return (
        <div className="flex flex-wrap items-center gap-1">
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button type="button" variant="outline" size="sm">
                        <TextQuote />
                        Insert template
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-64">
                    {templates.length === 0 && (
                        <p className="px-2 py-3 text-sm text-muted-foreground">
                            No templates yet. Save a caption or a set of
                            hashtags to reuse it here.
                        </p>
                    )}
                    {captions.length > 0 && (
                        <DropdownMenuGroup>
                            <DropdownMenuLabel>Captions</DropdownMenuLabel>
                            {captions.map((template) => (
                                <TemplateItem
                                    key={template.id}
                                    template={template}
                                    onSelect={onInsert}
                                />
                            ))}
                        </DropdownMenuGroup>
                    )}
                    {hashtagSets.length > 0 && (
                        <DropdownMenuGroup>
                            {captions.length > 0 && <DropdownMenuSeparator />}
                            <DropdownMenuLabel>Hashtag sets</DropdownMenuLabel>
                            {hashtagSets.map((template) => (
                                <TemplateItem
                                    key={template.id}
                                    template={template}
                                    onSelect={onInsert}
                                />
                            ))}
                        </DropdownMenuGroup>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link href={captionTemplatesIndex()}>
                            <Settings2 />
                            Manage templates
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <CaptionTemplateDialog
                defaultBody={currentCaption}
                trigger={
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={currentCaption.trim() === ''}
                    >
                        <BookmarkPlus />
                        Save as template
                    </Button>
                }
            />
        </div>
    );
}

function TemplateItem({
    template,
    onSelect,
}: {
    template: CaptionTemplate;
    onSelect: (template: CaptionTemplate) => void;
}) {
    return (
        <DropdownMenuItem
            onSelect={() => onSelect(template)}
            className="flex-col items-start gap-0.5"
        >
            <span className="flex items-center gap-1.5 font-medium">
                {template.type === 'hashtags' && <Hash className="size-3.5" />}
                {template.name}
            </span>
            <span className="line-clamp-1 w-full text-xs text-muted-foreground">
                {template.body}
            </span>
        </DropdownMenuItem>
    );
}
