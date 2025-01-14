<?php

namespace LaraZeus\Rhea\Filament\Pages;

use Corcel\Model\Post;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LaraZeus\Rhea\Forms\Components\ProgressBar;
use LaraZeus\Rhea\RheaPlugin;
use LaraZeus\Sky\SkyPlugin;

use Livewire\Component as Livewire;

class Importer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'zeus::pages.importer';

    public bool $truncate = false;

    public bool $overwrite = false;

    public bool $chunk = false;

    public $progress = 0;
    public $current = 0;
    public $count = 0;

    public $wpPosts;

    public static function getNavigationGroup(): ?string
    {
        return RheaPlugin::get()->getNavigationGroupLabel();
    }

    public function submit()
    {
        if (config('app.zeus-demo', false)) {
            Notification::make()
                ->title('this is just a demo')
                ->warning()
                ->send();

            return;
        }

        if ($this->truncate) {
            $posts = SkyPlugin::get()->getModel('Post')::get();
            $posts->each(function ($item, $key) {
                $item->tags()->detach();
                $item->delete();
            });
            Schema::disableForeignKeyConstraints();
            SkyPlugin::get()->getModel('Tag')::truncate();
            Schema::enableForeignKeyConstraints();

            Notification::make()
                ->title('all tables has been truncated')
                ->success()
                ->send();
        }

        $this->count = Post::where('post_status', '!=', 'auto-draft')->count();

        if ($this->chunk) {
            $posts = Post::where('post_status', '!=', 'auto-draft')->chunk(100, function ($posts) {
                foreach ($posts as $post) {
                    $this->processPost($post);
                }
            });
        }

        $posts = Post::where('post_status', '!=', 'auto-draft')->get();

        foreach ($posts as $post) {
            $this->processPost($post);
        }

        Notification::make()
            ->title('Done!')
            ->success()
            ->send();
    }

    public function processPost($post)
    {
        $zeusPost = $this->savePost($post);
        /** @phpstan-ignore-next-line */
        $tags = $post->taxonomies()->get();

        if (! $tags->isEmpty()) {
            $zeusPost->syncTagsWithType($tags->where('taxonomy', 'post_tag')->pluck('term.name')->toArray(), 'tag');
            $zeusPost->syncTagsWithType($tags->where('taxonomy', 'category')->pluck('term.name')->toArray(), 'category');
        }

        $this->updateProgress();
    }

    public function updateProgress()
    {
        $this->current++;
        $this->progress = ceil(($this->current / $this->count) * 100);
        $this->$refresh();
    }

    public function savePost($post)
    {
        $zeusPost = SkyPlugin::get()->getModel('Post')::findOrNew($post->ID);
        if (! $zeusPost->exists || $this->overwrite) {
            $zeusPost->id = $post->ID;
            $zeusPost->title = $post->post_title;
            $zeusPost->slug = (! empty($post->slug)) ? $post->slug : Str::slug($post->post_title);
            $zeusPost->description = $post->post_excerpt;
            $zeusPost->status = $post->post_status;
            $zeusPost->password = ! empty($post->post_password) ? $post->post_password : null;
            $zeusPost->post_type = $post->post_type;
            $zeusPost->content = $post->post_content;
            $zeusPost->user_id = auth()->user()->id; //$post->post_author;
            $zeusPost->parent_id = $post->post_parent;
            //$zeusPost->featured_image = $post->title;
            $zeusPost->created_at = $post->post_date;
            $zeusPost->published_at = $post->post_date;
            $zeusPost->save();
        }

        return $zeusPost;
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make()->id('main-card')->columns(2)->schema([
                Toggle::make('truncate')->label('Truncate')->helperText('truncate the current Posts table'),
                Toggle::make('overwrite')->label('Overwrite')->helperText('overwrite all existences posts'),
                Toggle::make('chunk')->label('Chunk')->helperText('import in chunks (useful when you have a lot of posts)'),
                ProgressBar::make('progress')->label('Progress')->live(),
            ]),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'wpPosts' => Post::where('post_status', '!=', 'auto-draft')->get(),
        ];
    }
}
