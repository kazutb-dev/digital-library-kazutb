<?php

namespace App\Http\Controllers\Librarian;

use App\Directory\ActiveDirectoryProvisioner;
use App\Directory\ActiveDirectoryService;
use App\Exceptions\ActiveDirectoryException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DirectoryReaderController extends Controller
{
    public function index(Request $request, ActiveDirectoryService $directory): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));
        $local = User::query()->with('readerProfile')->whereHas('roles', fn (Builder $query) => $query->where('name', 'member'))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $needle = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';
                $query->where(fn (Builder $scope) => $scope->whereRaw('LOWER(name) LIKE ?', [$needle])->orWhereRaw('LOWER(email) LIKE ?', [$needle]));
            })
            ->orderBy('name')->paginate(25)->withQueryString();
        $directoryUsers = [];
        $directoryError = null;
        if ($term !== '' && (bool) config('active_directory.enabled')) {
            try {
                $directoryUsers = $directory->search($term);
            } catch (ActiveDirectoryException $exception) {
                $directoryError = $exception->category;
            }
        }

        return view('librarian.readers.index', compact('local', 'directoryUsers', 'directoryError', 'term'));
    }

    public function provision(Request $request, ActiveDirectoryService $directory, ActiveDirectoryProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:128'],
            'category' => ['required', Rule::in(ReaderProfile::CATEGORIES)],
        ]);
        abort_unless((bool) config('active_directory.enabled'), 409);
        try {
            $login = $directory->normalizeLogin($data['login']);
            $identity = collect($directory->search($login, 2))->first(fn ($candidate) => mb_strtolower($candidate->samAccountName) === $login);
            abort_if($identity === null || ! $identity->enabled, 404);
            $user = $provisioner->provision($identity, $request, false);
            DB::transaction(function () use ($user, $data): void {
                ReaderProfile::query()->firstOrCreate(['user_id' => $user->getKey()], [
                    'ticket_number' => ReaderProfile::nextTicketNumber(),
                    'barcode' => ReaderProfile::nextBarcode(),
                    'category' => $data['category'],
                    'status' => 'active',
                ]);
            });
        } catch (ActiveDirectoryException) {
            return back()->withErrors(['q' => __('auth.provider_unavailable')]);
        }

        return redirect()->route('librarian.readers.index', ['q' => $user->email])->with('success', __('librarian.readers.provisioned'));
    }
}
