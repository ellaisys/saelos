<?php

namespace App\Http\Controllers;

use App\Events\ContactEmailed;
use App\Mail\Contact;
use App\Notifications\PersonEmailed;
use App\Notifications\PersonUpdated;
use App\User;
use Auth;
use App\Company;
use App\CustomField;
use App\CustomFieldValue;
use App\Events\ContactUpdated;
use App\Http\Resources\PersonCollection;
use App\Person;
use Illuminate\Http\Request;
use App\Http\Resources\Person as PersonResource;
use Illuminate\Support\Facades\Mail;

class PersonController extends Controller
{
    const INDEX_WITH = [
        'user',
        'company',
        'deals',
        'deals.people',
        'deals.notes',
        'deals.notes.user',
        'documents',
        'documents.user',
        'activities',
        'activities.details',
        'customFields',
        'customFields.customField',
        'notes',
        'notes.user',
    ];

    const SHOW_WITH = [
        'user',
        'company',
        'deals',
        'deals.people',
        'deals.notes',
        'deals.notes.user',
        'documents',
        'documents.user',
        'activities',
        'activities.details',
        'customFields',
        'customFields.customField',
        'notes',
        'notes.user',
    ];

    public function index(Request $request)
    {
        $people = Person::with(static::INDEX_WITH);

        $people->where('published', 1);
        $people->where(function($q) use ($request) {
            if ($firstName = $request->get('first_name')) {
                $q->orWhere('first_name', 'like', '%'.$firstName.'%');
            }

            if ($lastName = $request->get('last_name')) {
                $q->orWhere('last_name', 'like', '%'.$lastName.'%');
            }

            if ($email = $request->get('email')) {
                $q->orWhere('email', 'like', '%'.$email.'%');
            }
        });

        if ($modifiedSince = $request->get('modified_since')) {
            $people->where('updated_at', '>=', $modifiedSince);
        }

        $people->orderBy('id', 'desc');

        return new PersonCollection($people->paginate());
    }

    public function show($id)
    {
        return new PersonResource(Person::with(static::SHOW_WITH)->find($id));
    }

    /**
     * @param Request $request
     * @param         $id
     *
     * @TODO: Move company update to Model mutators
     *
     * @return Person
     * @throws \Exception
     */
    public function update(Request $request, $id)
    {
        /** @var Person $person */
        $person = Person::findOrFail($id);
        $data = $request->all();
        $personCompany = $data['company'] ?? [];
        $personUser = $data['user'] ?? null;
        $customFields = $data['custom_fields'] ?? [];

        if ($personCompany) {
            $company = array_key_exists('id', $personCompany)
                ? Company::findOrFail($personCompany['id'])
                : new Company;

            $company->update($personCompany);

            $data['company_id'] = $company->id;
        }

        if ($personUser) {
            $user = User::find($personUser);
        } else {
            $user = Auth::user();
        }

        if (isset($data['user_id']) && is_string($data['user_id']) && !is_numeric($data['user_id'])) {
            $user = User::where('name', $data['user_id'])->first();
            unset($data['user_id']);
        }

        $person->user()->associate($user);
        $person->update($data);
        $person->assignCustomFields($customFields);

        Auth::user()->notify(new PersonUpdated($person));
        ContactUpdated::broadcast($person);

        return $this->show($person->id);
    }

    /**
     * @param Request $request
     *
     * @return Person
     * @throws \Exception
     */
    public function store(Request $request)
    {
        $data = $request->all();

        if (isset($data['email'])) {
            $person = Person::where('email', '=', $data['email'])->first();
        }

        if (!isset($person)) {
            unset($data['user_id']);
            $person = Person::create($data);
        }

        return $this->update($request, $person->id);
    }

    /**
     * @param $id
     *
     * @return string
     * @throws \Exception
     */
    public function destroy($id)
    {
        Person::findOrFail($id)->delete();

        return '';
    }

    public function email(Request $request, int $id)
    {
        $person = Person::findOrFail($id);
        $user = Auth::user();
        $email = new Contact($request->get('emailContent'), $request->get('emailSubject'));

        Mail::to($person->email)
            ->send($email);

        ContactEmailed::dispatch($person, $user, $email);
        \Auth::user()->notify(new PersonEmailed($person));

        return 1;
    }
}
