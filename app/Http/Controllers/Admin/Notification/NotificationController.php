<?php

namespace App\Http\Controllers\Admin\Notification;

use App\Contracts\Repositories\NotificationRepositoryInterface;
use App\Enums\WebConfigKey;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\NotificationRequest;
use App\Services\NotificationService;
use App\Traits\FileManagerTrait;
use App\Traits\PushNotificationTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationController extends BaseController
{
    use PushNotificationTrait, FileManagerTrait {
        delete as deleteFile;
    }

    /**
     * @param NotificationRepositoryInterface $notificationRepo
     * @param NotificationService $notificationService
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepo,
        private readonly NotificationService             $notificationService,
    )
    {

    }

    /**
     * @param Request|null $request
     * @param string|null $type
     * @return View|Collection|LengthAwarePaginator|callable|RedirectResponse|null
     */
    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $searchValue = $request['searchValue'];
        $notifications = $this->notificationRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $searchValue,
            filters: ['sent_to' => 'customer'],
            dataLimit: getWebConfig(WebConfigKey::PAGINATION_LIMIT),
        );
        return view('admin-views.notification.index', compact('searchValue', 'notifications'));
    }

    /**
     * @param NotificationRequest $request
     * @return RedirectResponse
     */
    public function add(Request $request): RedirectResponse
    {
        return redirect()->route('admin.settings.notifications.index')
            ->with('success', 'استخدم معاينة الجمهور والنص ثم التأكيد للإرسال الإداري.');
    }

    /**
     * @param string|int $id
     * @return View
     */
    public function getUpdateView(string|int $id): View
    {
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $id]);
        return view('admin-views.notification.update-view', compact('notification'));
    }

    /**
     * @param NotificationRequest $request
     * @param string|int $id
     * @return RedirectResponse
     */
    public function update(NotificationRequest $request, string|int $id): RedirectResponse
    {
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $id]);
        $this->notificationRepo->update(id: $notification['id'],
            data: $this->notificationService->getNotificationUpdateData(
                request: $request, notificationImage: $notification['image']
            )
        );
        ToastMagic::success(translate('notification_updated_successfully'));
        return redirect()->route('admin.notification.index');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $request['id']]);
        $this->notificationRepo->update(id: $notification['id'], data: ['status' => $request['status']]);
        return response()->json([
            'message' => translate('Status_updated_successfully')
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function delete(Request $request): JsonResponse
    {
        $notification = $this->notificationRepo->getFirstWhere(params: ['id' => $request['id']]);
        $this->deleteFile('/notification/' . $notification['image']);
        $this->notificationRepo->delete(params: ['id' => $notification['id']]);
        return response()->json();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function resendNotification(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'استخدم صفحة الإشعارات لإرسال دفعة جديدة بعد المعاينة.'], 410);
    }
}
