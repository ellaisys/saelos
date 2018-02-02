<?php

namespace App\Workflows\Actions;

use App\Notifications\WorkflowNotification;
use App\User;
use App\WorkflowAction;
use Illuminate\Database\Eloquent\Model;

class Notify implements ActionInterface
{
    /**
     * @param Model $model
     * @param array $details
     *
     * @return array
     */
    public function execute(Model $model, array $details): array
    {
        $user = User::find($details['user_id']);

        $user->notify(new WorkflowNotification($details['message']));

        return $details;
    }

    public function updateActionDetails(WorkflowAction $action, array $details): bool
    {
        $action->details = $details;

        return $action->save();
    }


}