<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    protected $user;
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->user = $this->guard()->user();
    } //end __construct()

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tasks = $this->user->tasks()->get(['id', 'title', 'description', 'status', 'image_url', 'files_url']);
        foreach ($tasks as $task) {
            $task->files_url = json_decode($task->files_url);
        }
        return response()->json($tasks->toArray());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required|string',
                'description' => 'required|string',
                'status' => 'in:active,pending,done',
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'files.*' => 'mimes:doc,docx,pdf,txt|max:2048',
            ],
            [
                // 'image.required' => 'You have to choose a image!',
                // 'files.required' => 'You have to choose the files!',
                'status.required' => 'The status field is required and should be -> active | pending | done',
                'status.in' => 'The selected status is invalid. It should only be -> active | pending | done',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Create Task object
        $task = new Task();
        $task->title = $request->title;
        $task->description = $request->description;
        $task->status = $request->status;

        $pathImage = 'uploads' . DIRECTORY_SEPARATOR . 'us' . $this->user->id . DIRECTORY_SEPARATOR . 'images';
        $imageUrl = '';
        if ($request->hasFile('image')) {
            // store image into images folder
            // $file = $request->file('image')->store($pathImage);

            $uploadedImage = $request->file('image');
            $imageName = Str::random(30) . '.' . $uploadedImage->getClientOriginalExtension();
            $uploadedImage->move(public_path($pathImage), $imageName);
            $imageUrl = url($pathImage . DIRECTORY_SEPARATOR . $imageName);
            $task->image_url = $imageUrl;
        }

        $pathDocs = 'uploads' . DIRECTORY_SEPARATOR . 'us' . $this->user->id . DIRECTORY_SEPARATOR . 'documents';
        // $fileNames = collect([]);
        $fileUrls = collect([]);
        if ($request->hasFile('files')) {
            $files = $request->file('files');
            foreach ($files as $key => $file) {
                $fileKey = 'File_' . ($key + 1);
                $fileName = Str::random(30) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($pathDocs), $fileName);
                $fileUrl = url($pathDocs . DIRECTORY_SEPARATOR . $fileName);

                // $fileNames->put($fileKey, $fileName);
                $fileUrls->put($fileKey, $fileUrl);
            }
            $task->files_url = json_encode($fileUrls);
        }

        if ($this->user->tasks()->save($task)) {
            if ($fileUrls->isNotEmpty()) {
                $task->files_url = $fileUrls;
            }

            return response()->json([
                'status' => true,
                'task' => $task
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failure! Task could not be saved.'
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\Response
     */
    public function show(Task $task)
    {
        return response()->json($this->user->name);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\Response
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Task $task)
    {
        // Set POST method in Postman with PUT param method like => https://laravel-api.local/api/tasks/13?_method=PUT
        // So that we can get all the properties from 'form-data'

        // return response()->json([
        //     'status' => true,
        //     'requeted' => $request->all(),
        // ]);

        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required|string',
                'description' => 'required|string',
                'status' => 'in:active,pending,done',
                'image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'files.*' => 'mimes:doc,docx,pdf,txt|max:2048',
            ],
            [
                'status.required' => 'The status field is required and should be -> active | pending | done',
                'status.in' => 'The selected status is invalid. It should only be -> active | pending | done',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if user if authorized to change this record
        if ($task->user_id != $this->user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Failure! You are not authorized to make changes.'
            ]);
        }

        // Get old value to be removed
        $taskold = collect($task);
        $oldImage = $taskold->get('image_url');
        $oldFiles = json_decode($taskold->get('files_url'));

        // Update task
        $task->title = $request->title;
        $task->description = $request->description;
        $task->status = $request->status;

        $pathImage = 'uploads' . DIRECTORY_SEPARATOR . 'us' . $this->user->id . DIRECTORY_SEPARATOR . 'images';
        $imageUrl = '';
        if ($request->hasFile('image')) {
            // Remove old images
            if ($oldImage) {
                $imageToDelete = $pathImage . DIRECTORY_SEPARATOR . basename($oldImage);
                if (File::exists(public_path($imageToDelete))) {
                    File::delete($imageToDelete);
                }
            }

            // store image into images folder
            // $file = $request->file('image')->store($pathImage);
            $uploadedImage = $request->file('image');
            $imageName = Str::random(30) . '.' . $uploadedImage->getClientOriginalExtension();
            $uploadedImage->move(public_path($pathImage), $imageName);
            $imageUrl = url($pathImage . DIRECTORY_SEPARATOR . $imageName);
            $task->image_url = $imageUrl;
        }

        $pathFiles = 'uploads' . DIRECTORY_SEPARATOR . 'us' . $this->user->id . DIRECTORY_SEPARATOR . 'documents';
        $fileUrls = collect([]);
        if ($request->hasFile('files')) {
            // Remove old Files
            if (!empty($oldFiles)) {
                $filepath = array();
                foreach ($oldFiles as $file) {
                    $filesToDelete = $pathFiles . DIRECTORY_SEPARATOR . basename($file);
                    if (File::exists($filesToDelete)) {
                        // File::delete($filesToDelete);
                        array_push($filepath, $filesToDelete);
                    }
                }
                File::delete($filepath);
            }

            // Insert new files
            $files = $request->file('files');
            foreach ($files as $key => $file) {
                $fileKey = 'File_' . ($key + 1);
                $fileName = Str::random(30) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($pathFiles), $fileName);
                $fileUrl = url($pathFiles . DIRECTORY_SEPARATOR . $fileName);
                $fileUrls->put($fileKey, $fileUrl);
            }
            $task->files_url = json_encode($fileUrls);
        }

        if ($this->user->tasks()->save($task)) {
            if ($fileUrls->isNotEmpty()) {
                $task->files_url = $fileUrls;
            }

            return response()->json([
                'status' => true,
                'task' => $task
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failure! Task could not be saved.'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Task  $task
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, Task $task)
    {
        // Set param like => https://laravel-api.local/api/tasks/13?d=hard for hard deletion 
        // Set no param like => https://laravel-api.local/api/tasks/13 for soft deletion

        // Check if user if authorized to change this record
        if ($task->user_id != $this->user->id) {
            return response()->json([
                'status' => false,
                'message' => 'Failure! You are not authorized to delete this task.'
            ]);
        }

        // Hard-Deletion
        if ($request->d == 'hard') {
            if ($task->forceDelete()) {
                return response()->json(
                    [
                        'status' => true,
                        'task_deleted'   => $task,
                        'action' => 'hard deletion'
                    ]
                );
            } else {
                return response()->json(
                    [
                        'status'  => false,
                        'message' => 'Oops, the task could not be deleted.',
                    ]
                );
            }
        }

        // Soft-Deletion
        if ($task->delete()) {
            return response()->json(
                [
                    'status' => true,
                    'task_deleted'   => $task,
                    'action' => 'soft deletion'
                ]
            );
        } else {
            return response()->json(
                [
                    'status'  => false,
                    'message' => 'Oops, the task could not be deleted.',
                ]
            );
        }

    }

    protected function guard()
    {
        return Auth::guard();
    } //end guard()
}
