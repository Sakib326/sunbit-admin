<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Illuminate\Support\Facades\Hash;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Information')
                    ->description('Basic user account information')
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\DateTimePicker::make('email_verified_at')
                                    ->label('Email Verified At'),
                                Forms\Components\Select::make('role')
                                    ->options([
                                        'user' => 'Regular User',
                                        'agent' => 'Travel Agent',
                                        'admin' => 'Administrator',
                                    ])
                                    ->required()
                                    ->default('user')
                                    ->searchable(),
                            ])->columns(2),
                    ]),

                Section::make('Security')
                    ->description('User authentication and security settings')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => !empty($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => !empty($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->label(fn (string $operation): string =>
                                $operation === 'edit' ? 'New Password (leave blank to keep current)' : 'Password'),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->dehydrated(false)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255)
                            ->same('password'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'agent' => 'warning',
                        default => 'success',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'user' => 'Regular User',
                        'agent' => 'Travel Agent',
                        'admin' => 'Administrator',
                    ])
                    ->multiple(),
                TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->nullable()
                    ->attribute('email_verified_at'),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('resetPassword')
                        ->icon('heroicon-o-key')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->label('New Password')
                                ->password()
                                ->required()
                                ->rule('min:8'),
                            Forms\Components\TextInput::make('password_confirmation')
                                ->label('Confirm Password')
                                ->password()
                                ->required()
                                ->same('password'),
                        ])
                        ->action(function (User $user, array $data): void {
                            $user->update([
                                'password' => Hash::make($data['password']),
                            ]);

                            Notification::make()
                                ->title('Password reset successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('verifyEmail')
                        ->icon('heroicon-o-shield-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->hidden(fn (User $record): bool => $record->email_verified_at !== null)
                        ->action(function (User $user): void {
                            $user->email_verified_at = now();
                            $user->save();

                            Notification::make()
                                ->title('Email verified successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('verifyEmails')
                        ->icon('heroicon-o-shield-check')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;

                            $records->each(function (User $user) use (&$updated) {
                                if ($user->email_verified_at === null) {
                                    $user->email_verified_at = now();
                                    $user->save();
                                    $updated++;
                                }
                            });

                            Notification::make()
                                ->title("Verified {$updated} user emails")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('changeRole')
                        ->icon('heroicon-o-user-group')
                        ->form([
                            Forms\Components\Select::make('role')
                                ->label('Select Role')
                                ->options([
                                    'user' => 'Regular User',
                                    'agent' => 'Travel Agent',
                                    'admin' => 'Administrator',
                                ])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            // Don't allow changing your own role
                            $records = $records->filter(fn (User $user) => $user->id !== auth()->id());

                            $count = $records->count();

                            if ($count) {
                                $records->each(function (User $user) use ($data) {
                                    $user->update([
                                        'role' => $data['role'],
                                    ]);
                                });

                                Notification::make()
                                    ->title("Updated role for {$count} users")
                                    ->success()
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No Users Found')
            ->emptyStateDescription('Create a new user to get started.')
            ->emptyStateIcon('heroicon-o-users')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            // Add relation managers here when you build those models
            // RelationManagers\BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            // 'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    // Migration suggestion to be displayed when first viewed
    public static function getMigrationSuggestion(): string
    {
        return 'To enhance user management, consider adding these fields to users table:
            - profile_photo (string, nullable)
            - phone (string, nullable)
            - is_active (boolean, default: true)
            - last_login_at (timestamp, nullable)';
    }
}
