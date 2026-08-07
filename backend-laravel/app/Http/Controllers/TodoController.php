<?php

namespace App\Http\Controllers;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TodoController extends Controller
{
    public function index(Request $request)
    {
        $query = Todo::where('user_id', $request->user()->id);

        if ($request->has('completed')) {
            $query->where('completed', filter_var($request->query('completed'), FILTER_VALIDATE_BOOLEAN));
        }

        $todos = $query->orderBy('created_at', 'desc')->get();

        return response()->json($todos->map(fn (Todo $todo) => $this->formatTodo($todo)));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string'],
        ]);

        if ($validator->fails() || trim($request->input('title')) === '') {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['title' => ['Title is required']],
            ], 422);
        }

        $todo = Todo::create([
            'user_id' => $request->user()->id,
            'title' => trim($request->input('title')),
            'description' => $request->input('description'),
            'completed' => false,
        ]);

        return response()->json($this->formatTodo($todo), 201);
    }

    public function show(Request $request, string $id)
    {
        $todo = $this->findOwnedTodo($request, $id);

        if (!$todo) {
            return response()->json(['message' => 'Todo not found'], 404);
        }

        return response()->json($this->formatTodo($todo));
    }

    public function update(Request $request, string $id)
    {
        $todo = $this->findOwnedTodo($request, $id);

        if (!$todo) {
            return response()->json(['message' => 'Todo not found'], 404);
        }

        if ($request->has('title') && trim((string) $request->input('title')) === '') {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['title' => ['Title is required']],
            ], 422);
        }

        if ($request->has('completed') && !is_bool($request->input('completed'))) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['completed' => ['Completed must be true or false']],
            ], 422);
        }

        if ($request->has('title')) {
            $todo->title = trim($request->input('title'));
        }
        if ($request->has('description')) {
            $todo->description = $request->input('description');
        }
        if ($request->has('completed')) {
            $todo->completed = $request->input('completed');
        }
        $todo->save();

        return response()->json($this->formatTodo($todo));
    }

    public function destroy(Request $request, string $id)
    {
        $todo = $this->findOwnedTodo($request, $id);

        if (!$todo) {
            return response()->json(['message' => 'Todo not found'], 404);
        }

        $todo->delete();

        return response()->noContent();
    }

    private function findOwnedTodo(Request $request, string $id): ?Todo
    {
        if (Validator::make(['id' => $id], ['id' => 'uuid'])->fails()) {
            return null;
        }

        return Todo::where('id', $id)->where('user_id', $request->user()->id)->first();
    }

    private function formatTodo(Todo $todo)
    {
        return [
            'id' => $todo->id,
            'title' => $todo->title,
            'description' => $todo->description,
            'completed' => $todo->completed,
            'createdAt' => $todo->created_at,
            'updatedAt' => $todo->updated_at,
        ];
    }
}
