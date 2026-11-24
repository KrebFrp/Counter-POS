<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    // Real commits are required here so afterCommit file cleanup is exercised.
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_product_image_is_resized_reencoded_and_not_enlarged(): void
    {
        $this->post('/products', ['name' => 'Image product', 'price' => '1.00', 'stock_quantity' => 1, 'image' => UploadedFile::fake()->image('source.jpg', 1800, 900)])->assertSessionHasNoErrors();
        $product = Product::sole();
        Storage::disk('public')->assertExists($product->image);
        $info = getimagesize(Storage::disk('public')->path($product->image));
        $this->assertSame([1200, 600, IMAGETYPE_PNG], array_slice($info, 0, 3));
        $this->patch('/products/'.$product->id, ['name' => 'Image product', 'price' => '1.00', 'stock_quantity' => 1, 'original_stock' => 1, 'image' => UploadedFile::fake()->image('small.png', 80, 60)])->assertSessionHasNoErrors();
        $info = getimagesize(Storage::disk('public')->path($product->fresh()->image));
        $this->assertSame([80, 60], array_slice($info, 0, 2));
    }

    public function test_avatar_is_cropped_and_old_file_deleted_after_commit_but_retained_on_removal(): void
    {
        $data = ['username' => 'photo', 'full_name' => 'Photo Staff', 'password' => 'password', 'password_confirmation' => 'password', 'avatar' => UploadedFile::fake()->image('avatar.webp', 640, 320)];
        $this->post('/staff', $data)->assertSessionHasNoErrors();
        $user = User::where('username', 'photo')->sole();
        $old = $user->avatar;
        $info = getimagesize(Storage::disk('public')->path($old));
        $this->assertSame([256, 256, IMAGETYPE_PNG], array_slice($info, 0, 3));
        $this->patch('/staff/'.$user->id, ['username' => 'photo', 'full_name' => 'Photo Staff', 'avatar' => UploadedFile::fake()->image('next.png', 100, 200)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $new = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($new);
        $this->delete('/staff/'.$user->id)->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($new);
    }

    public function test_image_replacement_failure_cleans_new_file_and_preserves_previous_file(): void
    {
        $old = 'products/old.png';
        Storage::disk('public')->put($old, 'old-content');
        $product = Product::factory()->create(['image' => $old]);
        Product::updating(fn () => throw new \RuntimeException('Forced database failure.'));
        $this->withoutExceptionHandling();
        try {
            $this->patch('/products/'.$product->id, ['name' => 'Change', 'price' => '1.00', 'original_stock' => 10, 'stock_quantity' => 10, 'image' => UploadedFile::fake()->image('replacement.png')]);
            $this->fail('Expected the forced exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Forced database failure.', $exception->getMessage());
        } finally {
            Product::flushEventListeners();
        }
        $this->assertSame($old, $product->fresh()->image);
        $this->assertSame([$old], Storage::disk('public')->allFiles());
    }

    public function test_corrupt_disallowed_oversized_and_overdimension_images_are_rejected(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('corrupt.jpg', 'not an image'),
            UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;'),
            UploadedFile::fake()->image('large.png')->size(2049),
            UploadedFile::fake()->image('wide.png', 4097, 10),
        ];
        foreach ($files as $file) {
            $this->post('/products', ['name' => 'Invalid', 'price' => '1.00', 'stock_quantity' => 1, 'image' => $file])->assertSessionHasErrors('image');
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
