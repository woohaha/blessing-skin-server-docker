<?php

namespace App\Http\Controllers;

use App\Events\PlayerWasAdded;
use App\Events\PlayerWasDeleted;
use App\Events\PlayerWillBeAdded;
use App\Events\PlayerWillBeDeleted;
use App\Models\Player;
use App\Models\Texture;
use App\Models\User;
use App\Rules;
use Auth;
use Blessing\Filter;
use Blessing\Rejection;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            /** @var Player */
            $player = $request->route('player');
            if ($player->user->isNot($request->user())) {
                return json(trans('admin.players.no-permission'), 1)
                    ->setStatusCode(403);
            }

            return $next($request);
        }, [
            'only' => ['delete', 'rename', 'setTexture', 'clearTexture'],
        ]);
    }

    public function index(Filter $filter)
    {
        $grid = [
            'layout' => [
                ['md-6', 'md-6'],
            ],
            'widgets' => [
                [
                    [
                        'user.widgets.players.list',
                        'user.widgets.players.notice',
                    ],
                    ['shared.previewer'],
                ],
            ],
        ];
        $grid = $filter->apply('grid:user.player', $grid);

        /** @var User */
        $user = auth()->user();

        return view('user.player')
            ->with('grid', $grid)
            ->with('extra', [
                'count' => $user->players()->count(),
                'rule' => trans('user.player.player-name-rule.'.option('player_name_rule')),
                'length' => trans(
                    'user.player.player-name-length',
                    ['min' => option('player_name_length_min'), 'max' => option('player_name_length_max')]
                ),
                'score' => auth()->user()->score,
                'cost' => (int) option('score_per_player'),
            ]);
    }

    public function list()
    {
        return Auth::user()->players;
    }

    public function add(Request $request, Dispatcher $dispatcher, Filter $filter)
    {
        /** @var User */
        $user = Auth::user();

        $name = $request->validate([
            'name' => [
                'required',
                new Rules\PlayerName(),
                'min:'.option('player_name_length_min'),
                'max:'.option('player_name_length_max'),
                'unique:players',
            ],
        ])['name'];
        $name = $filter->apply('new_player_name', $name);

        $dispatcher->dispatch('player.add.attempt', [$name, $user]);

        $can = $filter->apply('can_add_player', true, [$name]);
        if ($can instanceof Rejection) {
            return json($can->getReason(), 1);
        }

        if ($user->score < (int) option('score_per_player')) {
            return json(trans('user.player.add.lack-score'), 7);
        }

        $dispatcher->dispatch('player.adding', [$name, $user]);
        event(new PlayerWillBeAdded($name));

        $player = new Player();
        $player->uid = $user->uid;
        $player->name = $name;
        $player->tid_skin = 0;
        $player->tid_cape = 0;
        $player->save();

        $user->score -= (int) option('score_per_player');
        $user->save();

        $dispatcher->dispatch('player.added', [$player, $user]);
        event(new PlayerWasAdded($player));

        return json(trans('user.player.add.success', ['name' => $name]), 0, $player->toArray());
    }

    public function delete(
        Dispatcher $dispatcher,
        Filter $filter,
        Player $player
    ) {
        /** @var User */
        $user = auth()->user();
        $playerName = $player->name;

        $dispatcher->dispatch('player.delete.attempt', [$player, $user]);

        $can = $filter->apply('can_delete_player', true, [$player]);
        if ($can instanceof Rejection) {
            return json($can->getReason(), 1);
        }

        $dispatcher->dispatch('player.deleting', [$player, $user]);
        event(new PlayerWillBeDeleted($player));

        $player->delete();

        if (option('return_score')) {
            $user->score += (int) option('score_per_player');
            $user->save();
        }

        $dispatcher->dispatch('player.deleted', [$player, $user]);
        event(new PlayerWasDeleted($playerName));

        return json(trans('user.player.delete.success', ['name' => $playerName]), 0);
    }

    public function rename(
        Request $request,
        Dispatcher $dispatcher,
        Filter $filter,
        Player $player
    ) {
        $name = $request->validate([
            'name' => [
                'required',
                new Rules\PlayerName(),
                'min:'.option('player_name_length_min'),
                'max:'.option('player_name_length_max'),
                Rule::unique('players')->ignoreModel($player),
            ],
        ])['name'];
        $name = $filter->apply('new_player_name', $name);

        $dispatcher->dispatch('player.renaming', [$player, $name]);

        $can = $filter->apply('can_rename_player', true, [$player, $name]);
        if ($can instanceof Rejection) {
            return json($can->getReason(), 1);
        }

        $old = $player->replicate();
        $player->name = $name;
        $player->save();

        $dispatcher->dispatch('player.renamed', [$player, $old]);

        return json(
            trans('user.player.rename.success', ['old' => $old->name, 'new' => $name]),
            0,
            $player->toArray()
        );
    }

    public function setTexture(
        Request $request,
        Dispatcher $dispatcher,
        Filter $filter,
        Player $player
    ) {
        /** @var User */
        $user = auth()->user();

        foreach (['skin', 'cape'] as $type) {
            $tid = $request->input($type);

            $can = $filter->apply('can_set_texture', true, [$player, $type, $tid]);
            if ($can instanceof Rejection) {
                return json($can->getReason(), 1);
            }

            if ($tid) {
                $texture = Texture::find($tid);
                if (empty($texture)) {
                    return json(trans('skinlib.non-existent'), 1);
                }

                if ($user->closet()->where('texture_tid', $tid)->doesntExist()) {
                    return json(trans('user.closet.remove.non-existent'), 1);
                }

                $dispatcher->dispatch('player.texture.updating', [$player, $texture]);

                $field = "tid_$type";
                $player->$field = $tid;
                $player->save();

                $dispatcher->dispatch('player.texture.updated', [$player, $texture]);
            }
        }

        return json(trans('user.player.set.success', ['name' => $player->name]), 0, $player->toArray());
    }

    public function clearTexture(
        Request $request,
        Dispatcher $dispatcher,
        Filter $filter,
        Player $player
    ) {
        $types = $request->input('type', []);

        foreach (['skin', 'cape'] as $type) {
            $can = $filter->apply('can_clear_texture', true, [$player, $type]);
            if ($can instanceof Rejection) {
                return json($can->getReason(), 1);
            }

            if ($request->has($type) || in_array($type, $types)) {
                $dispatcher->dispatch('player.texture.resetting', [$player, $type]);

                $field = "tid_$type";
                $player->$field = 0;
                $player->save();

                $dispatcher->dispatch('player.texture.reset', [$player, $type]);
            }
        }

        return json(trans('user.player.clear.success', ['name' => $player->name]), 0, $player->toArray());
    }
}
